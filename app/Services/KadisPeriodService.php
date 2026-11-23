<?php

namespace App\Services;

use App\Enums\Compliance;
use App\Enums\Level;
use App\Models\ComplianceRecord;
use App\Models\Direction;
use App\Models\DirectionRecipient;
use App\Models\LetterDisposition;
use App\Models\Position;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rekap mingguan/bulanan & nilai B-R-I-E-F untuk dashboard Kepala Dinas.
 *
 * Hari yang sudah lewat dibaca dari compliance_records (dievaluasi sekali lalu dikunci),
 * hari ini dihitung langsung.
 */
class KadisPeriodService
{
    /** @var array<string, string> */
    public const PERIODS = ['harian' => 'Harian', 'mingguan' => 'Mingguan', 'bulanan' => 'Bulanan'];

    /** @var array<string, string> */
    public const VALUES = ['B' => 'Berjenjang', 'R' => 'Runtut', 'I' => 'Integratif', 'E' => 'Efektif', 'F' => 'Fokus'];

    public function __construct(
        private KadisDashboardService $daily,
        private ComplianceService $compliance,
        private WorkCalendar $calendar,
    ) {}

    /** @return array{0: Carbon, 1: Carbon} rentang periode yang memuat $anchor (dipotong sampai hari ini) */
    public static function range(string $period, Carbon $anchor): array
    {
        [$from, $to] = $period === 'bulanan'
            ? [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()->startOfDay()]
            : [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()->startOfDay()];

        return [$from, $to->min(today())];
    }

    /** @return array{0: Carbon, 1: Carbon} periode sebelumnya (utuh) */
    public static function previous(string $period, Carbon $from): array
    {
        return $period === 'bulanan'
            ? [$from->copy()->subMonthNoOverflow()->startOfMonth(), $from->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()]
            : [$from->copy()->subWeek()->startOfWeek(), $from->copy()->subWeek()->endOfWeek()->startOfDay()];
    }

    public static function label(string $period, Carbon $from, Carbon $to): string
    {
        if ($period === 'bulanan') {
            return $from->translatedFormat('F Y');
        }

        $days = $from->isSameDay($to) ? $to->translatedFormat('d M') : $from->translatedFormat('d').'–'.$to->translatedFormat('d M');

        return 'Minggu ke-'.(int) ceil($from->day / 7).' '.$from->translatedFormat('F Y')." ({$days})";
    }

    // ---------------------------------------------------------------------
    // Data harian per jabatan
    // ---------------------------------------------------------------------

    /** Hitung & kunci evaluasi harian satu hari kerja yang sudah lewat. */
    public function evaluateDay(Carbon|string $date): int
    {
        $date = Carbon::parse($date)->startOfDay();

        if (! $date->lt(today()) || ! $this->calendar->isWorkday($date)) {
            return 0;
        }

        $this->compliance->forDate($date); // pastikan snapshot kepatuhan ada

        $pending = ComplianceRecord::query()->whereDate('date', $date->toDateString())->whereNull('evaluated_at')->exists();

        if (! $pending) {
            return 0;
        }

        $people = $this->daily->build($date)['people'];

        DB::transaction(function () use ($people, $date) {
            foreach ($people as $p) {
                ComplianceRecord::query()->whereDate('date', $date->toDateString())->where('position_id', $p['position_id'])->update([
                    'zone' => $p['zone'],
                    'needs_report' => $p['needs_report'],
                    'reported' => $p['reported'],
                    'reported_on_time' => $p['reported_on_time'],
                    'tasks_total' => $p['tasks_total'],
                    'tasks_done' => $p['tasks_done'],
                    'issues' => json_encode($p['issues']),
                    'evaluated_at' => now(),
                ]);
            }
        });

        return $people->count();
    }

    /**
     * Baris per jabatan per hari kerja pada rentang.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(Carbon $from, Carbon $to): Collection
    {
        [$unitOf, $units] = $this->daily->units();
        $days = $this->calendar->workdays($from, $to->copy()->min(today()));
        $past = array_values(array_filter($days, fn (string $d) => $d < today()->toDateString()));

        foreach ($past as $day) {
            $this->evaluateDay($day);
        }

        $rows = $past === [] ? collect() : ComplianceRecord::query()->whereIn(DB::raw('DATE(date)'), $past)->get()
            ->map(fn (ComplianceRecord $r) => [
                'date' => $r->date->toDateString(),
                'position_id' => $r->position_id,
                'user_id' => $r->user_id,
                'level' => Level::from($r->level),
                'unit_id' => $unitOf[$r->position_id] ?? 0,
                'status' => $r->status,
                'needs_report' => $r->needs_report,
                'reported' => $r->reported,
                'reported_on_time' => $r->reported_on_time,
                'tasks_total' => $r->tasks_total,
                'tasks_done' => $r->tasks_done,
                'zone' => $r->zone ?? 'none',
                'issues' => $r->issues ?? [],
            ]);

        if (in_array(today()->toDateString(), $days, true)) {
            $rows = $rows->concat($this->daily->build(today())['people']->map(fn (array $p) => [
                'date' => today()->toDateString(),
                ...collect($p)->only(['position_id', 'user_id', 'level', 'unit_id', 'status', 'needs_report', 'reported', 'reported_on_time', 'tasks_total', 'tasks_done', 'zone', 'issues'])->all(),
            ]));
        }

        return $rows->values();
    }

    /** @return array<string, int|null> */
    public function stats(Collection $rows): array
    {
        $staff = $rows->where('level', Level::Staff);
        $min = max(1, (int) Setting::get('workload_ideal_min'));
        $max = max($min, (int) Setting::get('workload_ideal_max'));

        return [
            'rows' => $rows->count(),
            'siap' => KadisDashboardService::pct($rows->where('status', Compliance::Patuh)->count(), $rows->whereNotIn('status', [Compliance::Menunggu])->count()),
            'lapor' => KadisDashboardService::pct($rows->sum('reported_on_time'), $rows->sum('needs_report')),
            'tuntas' => KadisDashboardService::pct($rows->sum('tasks_done'), $rows->sum('tasks_total')),
            'merata' => KadisDashboardService::pct($staff->filter(fn ($r) => $r['tasks_total'] >= $min && $r['tasks_total'] <= $max)->count(), $staff->count()),
            'tasks_total' => $rows->sum('tasks_total'),
            'tasks_done' => $rows->sum('tasks_done'),
            'violations' => $rows->filter(fn ($r) => $r['issues'] !== [])->count(),
        ];
    }

    /** Pegawai tidak patuh ≥ 2 hari pada rentang. */
    public function repeats(Collection $rows, int $minDays = 2): Collection
    {
        $positions = Position::query()->pluck('name', 'id');
        [, $units] = $this->daily->units();
        $groups = $rows->filter(fn ($r) => $r['issues'] !== [] && $r['user_id'])->groupBy('user_id')->filter(fn ($g) => $g->count() >= $minDays);
        $users = User::query()->whereIn('id', $groups->keys())->pluck('name', 'id');

        return $groups->map(function (Collection $group, $userId) use ($users, $positions, $units) {
            $issues = $group->pluck('issues')->flatten();
            $kinds = collect([
                'belum SIAP' => fn ($i) => str_starts_with($i, 'Belum SIAP'),
                'SIAP terlambat' => fn ($i) => str_starts_with($i, 'SIAP terlambat'),
                'belum/terlambat melapor' => fn ($i) => str_contains($i, 'melapor'),
                'luar area tanpa izin' => fn ($i) => str_starts_with($i, 'Lokasi'),
            ])->map(fn ($match) => $issues->filter($match)->count())->filter();
            $last = $group->sortBy('date')->last();

            return [
                'user_id' => (int) $userId,
                'name' => $users[$userId] ?? '—',
                'position' => $positions[$last['position_id']] ?? '—',
                'unit' => $units[$last['unit_id']] ?? 'Lainnya',
                'days' => $group->count(),
                'detail' => $kinds->map(fn ($n, $kind) => "{$n}× {$kind}")->implode(', '),
            ];
        })->sortByDesc('days')->values();
    }

    // ---------------------------------------------------------------------
    // Rekap periode
    // ---------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function report(string $period, Carbon $anchor): array
    {
        [$from, $to] = self::range($period, $anchor);
        [$prevFrom, $prevTo] = self::previous($period, $from);
        $rows = $this->rows($from, $to);
        $prevRows = $this->rows($prevFrom, $prevTo);
        $stats = $this->stats($rows);
        $prev = $this->stats($prevRows);
        $letters = $this->daily->lettersBetween($from, $to)->where('state', '!=', 'pending');
        $prevLetters = $this->daily->lettersBetween($prevFrom, $prevTo)->where('state', '!=', 'pending');
        $repeats = $this->repeats($rows);
        $prevRepeats = $this->repeats($prevRows)->count();
        [, $units] = $this->daily->units();
        $days = $this->calendar->workdays($from, $to);
        $delta = fn (?int $now, ?int $before, string $suffix = ' poin') => $now === null || $before === null ? 'Belum ada pembanding' : (($now - $before >= 0 ? '+' : '').($now - $before).$suffix.' dari '.($period === 'bulanan' ? 'bulan' : 'minggu').' lalu');
        $disposisi = KadisDashboardService::pct($letters->where('state', 'on_time')->count(), $letters->count());

        $cards = [
            ['label' => 'Rata-rata SIAP tepat waktu', 'value' => $stats['siap'], 'suffix' => '%', 'delta' => $delta($stats['siap'], $prev['siap']), 'status' => KadisDashboardService::status($stats['siap'], KadisDashboardService::target('siap')), 'metric' => 'siap'],
            ['label' => 'Penyelesaian tugas', 'value' => $stats['tuntas'], 'suffix' => '%', 'delta' => number_format($stats['tasks_done'], 0, ',', '.').' / '.number_format($stats['tasks_total'], 0, ',', '.').' tugas', 'status' => KadisDashboardService::status($stats['tuntas'], KadisDashboardService::target('tuntas')), 'metric' => 'tuntas'],
            ['label' => 'Melapor tepat waktu', 'value' => $stats['lapor'], 'suffix' => '%', 'delta' => $delta($stats['lapor'], $prev['lapor']), 'status' => KadisDashboardService::status($stats['lapor'], KadisDashboardService::target('lapor')), 'metric' => 'lapor'],
            ['label' => 'Disposisi surat tepat waktu', 'value' => $disposisi, 'suffix' => '%', 'delta' => $letters->where('state', 'on_time')->count().' / '.$letters->count().' surat', 'status' => KadisDashboardService::status($disposisi, KadisDashboardService::target('disposisi')), 'metric' => 'disposisi'],
            ['label' => 'Indeks pemerataan beban', 'value' => $stats['merata'], 'suffix' => '%', 'delta' => 'Target ≥ '.KadisDashboardService::target('merata').'%', 'status' => KadisDashboardService::status($stats['merata'], KadisDashboardService::target('merata')), 'metric' => 'merata'],
            ['label' => 'Tidak patuh berulang (≥ 2 hari)', 'value' => $repeats->count(), 'suffix' => ' orang', 'delta' => $prevRows->isEmpty() ? 'Belum ada pembanding' : match ($repeats->count() <=> $prevRepeats) {
                -1 => "Turun dari {$prevRepeats} orang", 0 => 'Sama dengan '.($period === 'bulanan' ? 'bulan' : 'minggu').' lalu', default => "Naik dari {$prevRepeats} orang"
            }, 'status' => $repeats->isEmpty() ? 'good' : ($repeats->count() <= $prevRepeats ? 'warn' : 'bad'), 'metric' => 'patuh'],
        ];

        // Tren: per hari (mingguan) atau per minggu (bulanan).
        $buckets = $period === 'bulanan'
            ? $rows->groupBy(fn ($r) => Carbon::parse($r['date'])->startOfWeek()->toDateString())->sortKeys()->values()
                ->map(fn ($g, $i) => ['label' => 'Mgg '.($i + 1), 'rows' => $g])
            : collect($days)->map(fn ($d) => ['label' => Carbon::parse($d)->translatedFormat('D'), 'rows' => $rows->where('date', $d)]);
        $trend = $buckets->map(fn ($b) => ['label' => $b['label'], ...collect($this->stats($b['rows']))->only(['siap', 'tuntas'])->all()])->values();

        $unitReport = collect($units)->map(function (string $name, int $id) use ($rows) {
            $s = $this->stats($rows->where('unit_id', $id));
            $statuses = collect(['siap', 'lapor', 'tuntas'])->map(fn ($k) => KadisDashboardService::status($s[$k], KadisDashboardService::target($k)))->reject(fn ($st) => $st === 'none');
            $overall = $statuses->contains('bad') ? 'bad' : ($statuses->contains('warn') ? 'warn' : ($statuses->isEmpty() ? 'none' : 'good'));

            return ['name' => $name, ...$s, 'status' => $overall, 'avg' => collect([$s['siap'], $s['lapor'], $s['tuntas']])->filter(fn ($v) => $v !== null)->avg()];
        })->filter(fn ($u) => $u['rows'] > 0)->values();

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'label' => self::label($period, $from, $to),
            'workdays' => count($days),
            'people' => $rows->pluck('position_id')->unique()->count(),
            'stats' => $stats,
            'cards' => $cards,
            'trend' => $trend,
            'repeats' => $repeats,
            'units' => $unitReport,
            'decision' => $this->decision($unitReport, $repeats, $rows->isEmpty()),
            'values' => $this->values($from, $to, $rows),
        ];
    }

    private function decision(Collection $units, Collection $repeats, bool $empty): string
    {
        if ($empty) {
            return 'Belum ada data penilaian pada periode ini.';
        }

        $good = $units->where('status', 'good')->pluck('name');
        $weak = $units->whereIn('status', ['bad', 'warn'])->sortBy('avg')->take(2)->pluck('name');
        $chronic = $repeats->where('days', '>=', 3)->count();

        return collect([
            $good->isNotEmpty() ? 'Berikan apresiasi kepada '.$good->join(', ', ' dan ').' yang mencapai seluruh target.' : null,
            $weak->isNotEmpty() ? 'Lakukan pembinaan dan penataan ulang pembagian tugas pada '.$weak->join(' dan ').'.' : 'Seluruh unit mencapai target; pertahankan ritme kerja.',
            $chronic ? "Panggil {$chronic} pegawai yang tidak patuh ≥ 3 hari untuk pengarahan langsung." : null,
        ])->filter()->implode(' ');
    }

    // ---------------------------------------------------------------------
    // Nilai B-R-I-E-F (0–100)
    // ---------------------------------------------------------------------

    /** @return array<string, array<string, mixed>> */
    public function values(Carbon $from, Carbon $to, ?Collection $rows = null): array
    {
        $rows ??= $this->rows($from, $to);
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        // Kolom tanggal di SQLite tersimpan dengan jam, jadi batas akhir memakai 23:59:59.
        $dateRange = [$start->toDateString(), $end->toDateString().' 23:59:59'];
        $positions = Position::query()->get(['id', 'name', 'level'])->keyBy('id');
        $kadisIds = $positions->filter(fn ($p) => $p->level === Level::Kadis)->keys();
        $users = User::query()->whereIn('id', $rows->pluck('user_id')->filter()->unique())->pluck('name', 'id');
        $verifyAt = fn (string $date) => Carbon::parse($date)->setTimeFromTimeString((string) Setting::get('verify_deadline'));
        $reportAt = fn (string $date) => Carbon::parse($date)->setTimeFromTimeString((string) Setting::get('report_deadline'));

        $recipients = DirectionRecipient::query()
            ->join('directions as d', 'd.id', '=', 'direction_recipients.direction_id')
            ->whereBetween('d.date', $dateRange)
            ->select(['direction_recipients.*', 'd.date as direction_date', 'd.type as direction_type', 'd.from_position_id', 'd.created_at as direction_created_at'])
            ->get();

        // B — arahan Kadis tepat waktu per hari kerja.
        $kadisDays = collect($this->calendar->workdays($from, $to))->reject(fn ($d) => $d === today()->toDateString()
            && now()->lt(Carbon::parse($d)->setTimeFromTimeString((string) Setting::get('deadline_kadis')))
            && ! Direction::query()->whereIn('from_position_id', $kadisIds)->whereDate('date', $d)->exists());
        $kadisFirst = Direction::query()->whereIn('from_position_id', $kadisIds)->whereBetween('date', $dateRange)
            ->selectRaw('DATE(date) as day, MIN(created_at) as first')->groupBy(DB::raw('DATE(date)'))->pluck('first', 'day');
        $kadisOnTime = $kadisDays->filter(fn ($d) => isset($kadisFirst[$d]) && Carbon::parse($kadisFirst[$d])->lte(Carbon::parse($d)->setTimeFromTimeString((string) Setting::get('deadline_kadis'))))->count();
        $level = fn (Level $l) => $rows->where('level', $l)->whereNotIn('status', [Compliance::Menunggu]);
        $late = $rows->whereIn('level', [Level::Eselon3, Level::Eselon4])->whereIn('status', [Compliance::Terlambat, Compliance::Tidak])
            ->groupBy('position_id')->map->count()->sortDesc()->take(3)->map(fn ($n, $pid) => ($positions[$pid]->name ?? '—')." ({$n}×)");

        // R — verifikasi laporan tepat waktu.
        $reported = $recipients->filter(fn ($r) => $r->reported_at !== null)
            ->reject(fn ($r) => $r->verified_at === null && Carbon::parse($r->direction_date)->isToday() && now()->lt($verifyAt($r->direction_date)));
        $verifiedOnTime = $reported->filter(fn ($r) => $r->verified_at && $r->verified_at->lte($verifyAt($r->direction_date)));
        $slowVerifiers = $reported->reject(fn ($r) => $r->verified_at && $r->verified_at->lte($verifyAt($r->direction_date)))
            ->groupBy('from_position_id')->map(fn ($g, $pid) => ($positions[$pid]->name ?? '—').': '.$g->count().' laporan')->take(3);

        // I — disposisi surat & penerusan arahan.
        $letters = $this->daily->lettersBetween($from, $to)->where('state', '!=', 'pending');
        $dispositions = LetterDisposition::query()->whereBetween('created_at', [$start, $end])->get(['to_position_id', 'replied_at', 'forwarded_at', 'completed_at']);
        $stalled = $dispositions->filter(fn ($d) => ! $d->replied_at && ! $d->forwarded_at && ! $d->completed_at);
        $officialRecipients = $recipients->filter(fn ($r) => in_array($positions->get($r->to_position_id)?->level, [Level::Eselon3, Level::Eselon4], true));
        $forwarded = $officialRecipients->isEmpty() ? collect() : Direction::query()->whereIn('parent_id', $officialRecipients->pluck('direction_id')->unique())
            ->get(['parent_id', 'from_position_id'])->map(fn ($d) => $d->parent_id.':'.$d->from_position_id)->flip();
        $forwardedCount = $officialRecipients->filter(fn ($r) => $forwarded->has($r->direction_id.':'.$r->to_position_id))->count();

        // E — efektif.
        $approved = $recipients->where('verification_status', 'approved');
        $stats = $this->stats($rows);
        $weakUnits = $this->unitCompletion($rows)->filter(fn ($u) => $u['pct'] !== null)->sortBy('pct')->take(2);

        // F — instruksi prioritas & tugas dari atasan.
        $priority = $recipients->where('direction_type', 'priority');
        $quick = $priority->filter(fn ($r) => $r->confirmed_at && $r->confirmed_at->lte(Carbon::parse($r->direction_created_at)->addMinutes(30)));
        $priorityDone = $priority->filter(fn ($r) => $r->reported_at && $r->reported_at->lte($reportAt($r->direction_date)));
        $manual = Task::query()->toBase()->join('direction_recipients as r', 'r.id', '=', 'tasks.direction_recipient_id')->join('directions as d', 'd.id', '=', 'r.direction_id')
            ->whereBetween('d.date', $dateRange)->where('tasks.source', 'manual')
            ->selectRaw('COUNT(*) as total, SUM(tasks.status = ?) as done', ['selesai'])->first();

        $definitions = [
            'B' => [
                'meaning' => 'Arahan mengalir tertib dari Kepala Dinas ke Eselon 3, lalu Eselon 4, lalu staf sesuai batas jam masing-masing.',
                'parts' => [
                    ['Kepala Dinas mengirim arahan ≤ '.str_replace(':', '.', (string) Setting::get('deadline_kadis')), 20, $kadisOnTime, $kadisDays->count(), "{$kadisOnTime} dari {$kadisDays->count()} hari kerja"],
                    ['Eselon 3 tepat waktu (≤ '.str_replace(':', '.', (string) Setting::get('deadline_eselon3')).')', 40, $level(Level::Eselon3)->where('status', Compliance::Patuh)->count(), $level(Level::Eselon3)->count(), null],
                    ['Eselon 4 tepat waktu (≤ '.str_replace(':', '.', (string) Setting::get('deadline_eselon4')).')', 40, $level(Level::Eselon4)->where('status', Compliance::Patuh)->count(), $level(Level::Eselon4)->count(), null],
                ],
                'cause' => $late->isEmpty() ? 'Seluruh pejabat bertindak tepat waktu pada periode ini.' : 'Pejabat yang paling sering terlambat atau tidak beraksi: '.$late->join(', ').'.',
                'fixes' => ['Aktifkan pengingat WhatsApp batas jam instruksi (Admin → WhatsApp) agar pejabat diingatkan sebelum batas.', 'Minta pejabat yang dinas luar pagi menunjuk pelaksana harian untuk meneruskan arahan.'],
                'action' => ['route' => route('kadis.compliance'), 'label' => 'Tegur pejabat yang belum tepat waktu'],
            ],
            'R' => [
                'meaning' => 'Pekerjaan mengikuti urutan: SIAP pagi, tugas dikerjakan, laporan sebelum '.str_replace(':', '.', (string) Setting::get('report_deadline')).', lalu diverifikasi atasan sebelum '.str_replace(':', '.', (string) Setting::get('verify_deadline')).'.',
                'parts' => [
                    ['Pegawai SIAP sebelum batas jam', 35, $rows->where('status', Compliance::Patuh)->count(), $rows->whereNotIn('status', [Compliance::Menunggu])->count(), null],
                    ['Laporan dikirim sebelum batas laporan', 35, $rows->sum('reported_on_time'), $rows->sum('needs_report'), null],
                    ['Laporan diverifikasi atasan sebelum batas verifikasi', 30, $verifiedOnTime->count(), $reported->count(), null],
                ],
                'cause' => $slowVerifiers->isEmpty() ? 'Laporan diverifikasi tepat waktu.' : 'Laporan yang belum/terlambat diverifikasi menumpuk di: '.$slowVerifiers->join('; ').'.',
                'fixes' => ['Sediakan waktu khusus verifikasi menjelang batas verifikasi bagi Eselon 3/4.', 'Ingatkan pegawai yang sudah SIAP tetapi belum melapor sebelum batas laporan.'],
                'action' => ['route' => route('kadis.metric', 'lapor'), 'label' => 'Lihat status laporan'],
            ],
            'I' => [
                'meaning' => 'Koordinasi berjalan tanpa tertahan: surat masuk segera didisposisi dan ditindaklanjuti, arahan atasan diteruskan ke bawahan.',
                'parts' => [
                    ['Surat masuk didisposisi tepat waktu', 40, $letters->where('state', 'on_time')->count(), $letters->count(), null],
                    ['Disposisi dibalas, diteruskan, atau diselesaikan', 30, $dispositions->count() - $stalled->count(), $dispositions->count(), null],
                    ['Arahan atasan diteruskan pejabat ke bawahan', 30, $forwardedCount, $officialRecipients->count(), null],
                ],
                'cause' => collect([
                    $letters->where('state', 'late')->count() ? $letters->where('state', 'late')->count().' surat terlambat didisposisi' : null,
                    $stalled->isNotEmpty() ? $stalled->count().' disposisi belum ditindaklanjuti ('.$stalled->groupBy('to_position_id')->map(fn ($g, $pid) => $positions[$pid]->name ?? '—')->take(3)->join(', ').')' : null,
                    $officialRecipients->count() - $forwardedCount > 0 ? ($officialRecipients->count() - $forwardedCount).' arahan belum diteruskan ke bawahan' : null,
                ])->filter()->join('; ') ?: 'Tidak ada surat atau arahan yang tertahan.',
                'fixes' => ['Pastikan Kasubag Umum memaraf surat di hari yang sama agar Kepala Dinas bisa segera mendisposisi.', 'Minta penerima disposisi membalas atau meneruskan paling lambat esok hari kerja.'],
                'action' => ['route' => route('arsip.index'), 'label' => 'Buka E-Arsip'],
            ],
            'E' => [
                'meaning' => 'Target tercapai dengan beban kerja merata — bukan ditopang segelintir pegawai — dan laporan diterima tanpa revisi berulang.',
                'parts' => [
                    ['Penyelesaian tugas', 45, $stats['tasks_done'], $stats['tasks_total'], number_format($stats['tasks_done'], 0, ',', '.').'/'.number_format($stats['tasks_total'], 0, ',', '.').' tugas'],
                    ['Pemerataan beban kerja staf', 35, $stats['merata'] ?? 0, $stats['merata'] === null ? 0 : 100, $stats['merata'] === null ? null : 'staf di rentang beban ideal'],
                    ['Laporan diterima tanpa revisi', 20, $approved->where('revision_count', 0)->count(), $approved->count(), null],
                ],
                'cause' => $weakUnits->isEmpty() ? 'Belum ada tugas pada periode ini.' : 'Penyelesaian terendah: '.$weakUnits->map(fn ($u) => "{$u['name']} ({$u['pct']}%)")->join(', ').'. '
                    .$rows->where('level', Level::Staff)->where('tasks_total', 0)->count().' hari-staf tanpa tugas tercatat.',
                'fixes' => ['Minta Eselon 4 meratakan pembagian tugas: setiap staf memegang '.Setting::get('workload_ideal_min').'–'.Setting::get('workload_ideal_max').' tugas per hari.', 'Lengkapi tupoksi setiap jabatan agar tugas rutin dapat dipilih saat membagi tugas.'],
                'action' => ['route' => route('kadis.metric', 'merata'), 'label' => 'Lihat pemerataan beban'],
            ],
            'F' => [
                'meaning' => 'Instruksi prioritas Kepala Dinas dan tugas dari atasan dituntaskan lebih dulu sebelum pekerjaan lain.',
                'parts' => [
                    ['Instruksi prioritas dikonfirmasi ≤ 30 menit', 30, $quick->count(), $priority->count(), null],
                    ['Instruksi prioritas dilaporkan sebelum batas laporan', 40, $priorityDone->count(), $priority->count(), null],
                    ['Tugas dari atasan (berjenjang) tuntas', 30, (int) ($manual->done ?? 0), (int) ($manual->total ?? 0), null],
                ],
                'cause' => $priority->isEmpty() ? 'Tidak ada instruksi prioritas pada periode ini.'
                    : ($priority->count() - $priorityDone->count() ? ($priority->count() - $priorityDone->count()).' instruksi prioritas belum dilaporkan tepat waktu: '.$priority->reject(fn ($r) => $priorityDone->contains('id', $r->id))->map(fn ($r) => $positions[$r->to_position_id]->name ?? '—')->unique()->take(3)->join(', ').'.' : 'Seluruh instruksi prioritas dilaporkan tepat waktu.'),
                'fixes' => ['Gunakan instruksi prioritas hanya untuk hal mendesak (maks. 3 per hari) agar penerima fokus.', 'Pantau instruksi prioritas yang belum dikonfirmasi 30 menit setelah dikirim.'],
                'action' => ['route' => route('kadis.directions'), 'label' => 'Pantau instruksi prioritas'],
            ],
        ];

        return collect($definitions)->map(function (array $def, string $key) {
            $parts = collect($def['parts'])->map(fn ($p) => [
                'label' => $p[0],
                'weight' => $p[1],
                'score' => KadisDashboardService::pct($p[2], $p[3]),
                'detail' => $p[4] ?? ($p[3] ? "{$p[2]} dari {$p[3]}" : 'belum ada data'),
            ]);
            $measured = $parts->whereNotNull('score');
            $score = $measured->isEmpty() ? null : (int) round($measured->sum(fn ($p) => $p['score'] * $p['weight']) / $measured->sum('weight'));

            return [
                'key' => $key,
                'name' => self::VALUES[$key],
                'score' => $score,
                'status' => match (true) {
                    $score === null => 'none', $score >= 80 => 'good', $score >= 70 => 'warn', default => 'bad'
                },
                ...$def,
                'parts' => $parts->all(),
            ];
        })->all();
    }

    private function unitCompletion(Collection $rows): Collection
    {
        [, $units] = $this->daily->units();

        return collect($units)->map(fn ($name, $id) => [
            'name' => $name,
            'pct' => KadisDashboardService::pct($rows->where('unit_id', $id)->sum('tasks_done'), $rows->where('unit_id', $id)->sum('tasks_total')),
        ]);
    }

    /** Kalimat kesimpulan: dua nilai terendah. */
    public static function conclusion(array $values): string
    {
        $scored = collect($values)->whereNotNull('score')->sortBy('score');

        if ($scored->isEmpty()) {
            return 'Belum ada data untuk menghitung nilai BRIEF.';
        }

        $low = $scored->take(2)->map(fn ($v) => "{$v['name']} ({$v['score']})")->join(' dan ');

        return $scored->first()['score'] >= 80
            ? "Semua nilai baik; terendah {$low}. Ketuk huruf untuk melihat asal skor."
            : "{$low} paling rendah. Ketuk huruf mana pun untuk melihat asal skor, penyebab, dan upaya perbaikan.";
    }
}
