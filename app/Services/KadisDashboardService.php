<?php

namespace App\Services;

use App\Enums\Compliance;
use App\Enums\Level;
use App\Enums\TaskStatus;
use App\Models\Direction;
use App\Models\DirectionRecipient;
use App\Models\FieldAssignment;
use App\Models\Initiative;
use App\Models\Letter;
use App\Models\LocationLog;
use App\Models\Position;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\BriefNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Dashboard akuntabilitas harian Kepala Dinas.
 *
 * Seluruh angka dihitung dari data yang ada (kepatuhan, instruksi, tugas, lokasi, inisiatif, E-Arsip)
 * untuk satu tanggal. "Pegawai" = jabatan terisi yang dinilai (Eselon 3, Eselon 4, Staff);
 * "unit" = pohon jabatan di bawah setiap Eselon 3 (Sekretariat / Bidang).
 */
class KadisDashboardService
{
    /** @var array<string, string> */
    public const METRICS = [
        'siap' => 'Kepatuhan SIAP',
        'lapor' => 'Sudah melapor',
        'tuntas' => 'Penyelesaian tugas',
        'disposisi' => 'Disposisi surat',
        'merata' => 'Pemerataan beban',
        'lokasi' => 'Posisi pegawai',
        'inisiatif' => 'Tugas inisiatif',
        'patuh' => 'Tidak patuh',
    ];

    /** @var array<string, array{0:string, 1:string, 2:string}> warna [hex, chip, teks] per status */
    public const TONES = [
        'good' => ['#147346', 'bg-emerald-100 text-emerald-700', 'text-emerald-700'],
        'warn' => ['#D19C2D', 'bg-amber-100 text-amber-800', 'text-amber-700'],
        'bad' => ['#B3261E', 'bg-rose-100 text-rose-700', 'text-rose-600'],
        'none' => ['#94A3B8', 'bg-slate-100 text-slate-500', 'text-slate-500'],
    ];

    public const ZONES = [
        'office' => ['Di kantor', '#147346'],
        'field' => ['Dinas luar', '#1A4272'],
        'outside' => ['Luar area, tanpa izin', '#B3261E'],
        'none' => ['Belum merekam lokasi', '#94A3B8'],
    ];

    public function __construct(private ComplianceService $compliance, private WorkCalendar $calendar) {}

    public static function target(string $key): int
    {
        return (int) Setting::get('target_'.$key);
    }

    public static function pct(int $part, int $total): ?int
    {
        return $total > 0 ? (int) round($part / $total * 100) : null;
    }

    public static function status(?int $pct, int $target): string
    {
        return match (true) {
            $pct === null => 'none',
            $pct >= $target => 'good',
            $pct >= $target - 10 => 'warn',
            default => 'bad',
        };
    }

    public static function tone(string $status, int $index = 1): string
    {
        return self::TONES[$status][$index] ?? self::TONES['none'][$index];
    }

    private static function time(string $setting): string
    {
        return str_replace(':', '.', (string) Setting::get($setting));
    }

    /** @return array<string, mixed> */
    public function build(Carbon|string $date): array
    {
        $date = Carbon::parse($date)->startOfDay();
        $day = $date->toDateString();
        $isToday = $date->isToday();
        $reportDeadline = $date->copy()->setTimeFromTimeString((string) Setting::get('report_deadline'));
        $reportClosed = ! $isToday || now()->gte($reportDeadline);

        $positions = Position::query()->get(['id', 'name', 'unit', 'level', 'parent_id', 'sort_order'])->keyBy('id');
        [$unitOf, $units] = $this->units($positions);

        $rows = $this->compliance->forDate($date);
        $userIds = $rows->pluck('user_id')->filter()->unique()->values();
        $users = User::query()->whereIn('id', $userIds)->get(['id', 'name'])->keyBy('id');

        $recipients = DirectionRecipient::query()
            ->join('directions as d', 'd.id', '=', 'direction_recipients.direction_id')
            ->whereDate('d.date', $day)
            ->select([
                'direction_recipients.id', 'direction_recipients.to_position_id', 'direction_recipients.to_user_id',
                'direction_recipients.confirmed_at', 'direction_recipients.reported_at', 'direction_recipients.verification_status',
                'd.type as direction_type', 'd.from_position_id',
            ])
            ->withCount('tasks')
            ->get();

        $tasks = Task::query()->toBase()
            ->join('direction_recipients as r', 'r.id', '=', 'tasks.direction_recipient_id')
            ->join('directions as d', 'd.id', '=', 'r.direction_id')
            ->whereDate('d.date', $day)
            ->get(['tasks.status', 'tasks.source', 'r.to_position_id', 'r.to_user_id']);

        $logs = LocationLog::query()->toBase()->whereIn('user_id', $userIds)->whereDate('created_at', $day)
            ->orderBy('id')->get(['user_id', 'zone', 'distance_m'])->groupBy('user_id');
        $field = FieldAssignment::query()->toBase()->whereIn('user_id', $userIds)->whereDate('date', $day)
            ->get(['user_id', 'destination'])->keyBy('user_id');

        $recipientsByPosition = $recipients->groupBy('to_position_id');
        $tasksByPosition = $tasks->groupBy('to_position_id');
        $reminded = $isToday ? $this->remindedToday($userIds) : collect();

        $people = $rows->map(function (array $row, int $positionId) use ($positions, $unitOf, $units, $users, $recipientsByPosition, $tasksByPosition, $logs, $field, $reportDeadline, $reportClosed, $reminded) {
            $position = $positions->get($positionId);
            $level = $row['level'];
            $userId = $row['user_id'];
            $status = $row['status'] instanceof Compliance ? $row['status'] : Compliance::from($row['status']);
            $actedAt = $row['acted_at'] ? Carbon::parse($row['acted_at']) : null;
            $isStaff = $level === Level::Staff;

            // Staf hanya dihitung dari instruksi untuk dirinya (riwayat jabatan staf tidak diwariskan).
            $mine = ($recipientsByPosition->get($positionId) ?? collect())->filter(fn ($r) => ! $isStaff || (int) $r->to_user_id === (int) $userId);
            $myTasks = ($tasksByPosition->get($positionId) ?? collect())->filter(fn ($t) => ! $isStaff || (int) $t->to_user_id === (int) $userId);

            // Laporan wajib bila instruksi berisi tugas atau berupa instruksi prioritas.
            $needs = $mine->filter(fn ($r) => $r->tasks_count > 0 || $r->direction_type === 'priority');
            $reported = $needs->filter(fn ($r) => $r->reported_at !== null);
            $onTime = $reported->filter(fn ($r) => $r->reported_at->lte($reportDeadline));

            $userLogs = $logs->get($userId) ?? collect();
            $fieldDuty = $field->get($userId);
            $outside = $fieldDuty ? collect() : $userLogs->where('zone', 'outside');
            $zone = match (true) {
                $outside->isNotEmpty() => 'outside',
                $fieldDuty !== null => 'field',
                default => $userLogs->last()?->zone ?? 'none',
            };

            $deadline = str_replace(':', '.', (string) $row['deadline']);
            $issues = [];

            if ($status === Compliance::Tidak) {
                $issues[] = 'Belum SIAP (batas '.$deadline.')';
            } elseif ($status === Compliance::Terlambat) {
                $issues[] = 'SIAP terlambat '.$actedAt?->format('H.i').' (batas '.$deadline.')';
            }

            if ($reportClosed && $needs->isNotEmpty()) {
                $missing = $needs->count() - $reported->count();
                if ($missing > 0) {
                    $issues[] = 'Belum melapor sampai '.$reportDeadline->format('H.i').($needs->count() > 1 ? " ({$missing} dari {$needs->count()} instruksi)" : '');
                } elseif ($onTime->count() < $reported->count()) {
                    $issues[] = 'Melapor terlambat (lewat '.$reportDeadline->format('H.i').')';
                }
            }

            if ($zone === 'outside') {
                $issues[] = 'Lokasi di luar area tanpa izin dinas luar ('.number_format($outside->max('distance_m') / 1000, 1, ',', '.').' km)';
            }

            $unitId = $unitOf[$positionId] ?? 0;

            return [
                'position_id' => $positionId,
                'position' => $position?->name ?? '—',
                'level' => $level,
                'unit_id' => $unitId,
                'unit' => $units[$unitId] ?? 'Lainnya',
                'user_id' => $userId,
                'name' => $users->get($userId)?->name ?? '—',
                'status' => $status,
                'acted_at' => $actedAt,
                'deadline' => $deadline,
                'needs_report' => $needs->count(),
                'reported' => $reported->count(),
                'reported_on_time' => $onTime->count(),
                'approved' => $reported->where('verification_status', 'approved')->count(),
                'revision' => $reported->where('verification_status', 'revision')->count(),
                'tasks_total' => $myTasks->count(),
                'tasks_done' => $myTasks->where('status', TaskStatus::Selesai->value)->count(),
                'zone' => $zone,
                'field' => $fieldDuty?->destination,
                'issues' => $issues,
                'reminded_at' => $reminded->get($userId),
            ];
        })->sortBy(fn ($p) => [$p['level']->rank(), $p['unit'], $p['position']])->values();

        return [
            'date' => $date,
            'is_today' => $isToday,
            'workday' => $this->calendar->isWorkday($date),
            'report_closed' => $reportClosed,
            'provisional' => $isToday && now()->lt($date->copy()->setTimeFromTimeString((string) Setting::get('verify_deadline'))),
            'units' => $units,
            'people' => $people,
            'tasks' => $tasks->map(fn ($t) => ['status' => $t->status, 'source' => $t->source, 'unit_id' => $unitOf[$t->to_position_id] ?? 0]),
            'kadis' => $this->kadisDirections($positions, $recipients, $day, $date),
            'initiatives' => Initiative::query()->whereDate('created_at', $day)->with('user:id,name', 'position:id,name')->latest('id')->get()
                ->map(fn (Initiative $i) => ['title' => $i->title, 'status' => $i->status, 'name' => $i->user?->name, 'position' => $i->position?->name, 'unit_id' => $unitOf[$i->position_id] ?? 0]),
            'letters' => $this->letters($day),
        ];
    }

    /**
     * Unit = Eselon 3 teratas pada rantai atasan.
     *
     * @return array{0: array<int, int>, 1: array<int, string>}
     */
    public function units(?Collection $positions = null): array
    {
        $positions ??= Position::query()->get(['id', 'name', 'unit', 'level', 'parent_id', 'sort_order'])->keyBy('id');
        $units = $positions->filter(fn (Position $p) => $p->level === Level::Eselon3)
            ->sortBy(fn (Position $p) => [$p->sort_order, $p->id])
            ->mapWithKeys(fn (Position $p) => [$p->id => $p->unit ?: $p->name])
            ->all();

        $unitOf = [];
        foreach ($positions as $position) {
            $cursor = $position;
            $guard = 0;
            while ($cursor && $cursor->level !== Level::Eselon3 && $guard++ < 20) {
                $cursor = $positions->get($cursor->parent_id);
            }
            $unitOf[$position->id] = $cursor?->level === Level::Eselon3 ? $cursor->id : 0;
        }

        if (in_array(0, $unitOf, true)) {
            $units[0] = 'Lainnya';
        }

        return [$unitOf, $units];
    }

    /** @return array<string, mixed> */
    private function kadisDirections(Collection $positions, Collection $recipients, string $day, Carbon $date): array
    {
        $kadisIds = $positions->filter(fn (Position $p) => $p->level === Level::Kadis)->keys();
        $directions = Direction::query()->whereIn('from_position_id', $kadisIds)->whereDate('date', $day)->orderBy('created_at')->get(['id', 'type', 'created_at']);
        $deadline = $date->copy()->setTimeFromTimeString((string) Setting::get('deadline_kadis'));
        $first = $directions->first()?->created_at;
        $mine = $recipients->whereIn('from_position_id', $kadisIds->all());

        return [
            'sent_at' => $first,
            'deadline' => $deadline->format('H.i'),
            'on_time' => $first ? $first->lte($deadline) : null,
            'count' => $directions->count(),
            'priority' => $directions->where('type', 'priority')->count(),
            'recipients' => $mine->count(),
            'confirmed' => $mine->filter(fn ($r) => $r->confirmed_at !== null)->count(),
        ];
    }

    /** Surat masuk tanggal ini & ketepatan disposisinya. */
    private function letters(string $day): Collection
    {
        return $this->lettersBetween(Carbon::parse($day), Carbon::parse($day));
    }

    /** Surat masuk yang diinput pada rentang tanggal & ketepatan disposisinya. */
    public function lettersBetween(Carbon $from, Carbon $to): Collection
    {
        $hours = max(1, (int) Setting::get('disposition_hours'));
        $urgentHours = max(1, (int) Setting::get('disposition_urgent_hours'));

        return Letter::query()->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->orderBy('created_at')
            ->get(['id', 'agenda_number', 'subject', 'sender', 'nature', 'created_at', 'paraf_at', 'disposed_at'])
            ->map(function (Letter $letter) use ($hours, $urgentHours) {
                $urgent = in_array($letter->nature, ['segera', 'sangat_segera'], true);
                $due = $letter->created_at->copy()->addHours($urgent ? $urgentHours : $hours);
                $state = match (true) {
                    $letter->disposed_at !== null => $letter->disposed_at->lte($due) ? 'on_time' : 'late',
                    now()->gt($due) => 'late',
                    default => 'pending',
                };

                return [
                    'id' => $letter->id,
                    'agenda' => $letter->agenda_number,
                    'subject' => $letter->subject,
                    'sender' => $letter->sender,
                    'urgent' => $urgent,
                    'nature' => Letter::NATURES[$letter->nature] ?? $letter->nature,
                    'due' => $due,
                    'state' => $state,
                    'stage' => match (true) {
                        $letter->disposed_at !== null => 'Terdisposisi '.$letter->disposed_at->format('H.i'),
                        $letter->paraf_at !== null => 'Menunggu disposisi Kepala Dinas',
                        default => 'Menunggu paraf',
                    },
                ];
            });
    }

    // ---------------------------------------------------------------------
    // Ringkasan untuk halaman utama
    // ---------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function summary(array $data): array
    {
        $people = $data['people'];
        $total = $people->count();
        $patuh = $people->where('status', Compliance::Patuh)->count();
        $acted = $people->whereIn('status', [Compliance::Patuh, Compliance::Terlambat])->count();

        $tasks = $data['tasks'];
        $tasksDone = $tasks->where('status', TaskStatus::Selesai->value)->count();

        $needing = $people->where('needs_report', '>', 0);
        $reportedPeople = $people->where('reported', '>', 0)->count();
        $reportedOnTime = $needing->filter(fn ($p) => $p['reported_on_time'] === $p['needs_report'])->count();

        $letters = $data['letters']->where('state', '!=', 'pending');
        $workload = $this->workload($people);

        $donuts = [
            'siap' => ['title' => 'Kepatuhan SIAP', 'pct' => self::pct($patuh, $total), 'target' => self::target('siap'), 'actual' => "{$patuh}/{$total} tepat waktu", 'target_label' => 'Target ≥ '.self::target('siap').'%'],
            'tuntas' => ['title' => 'Penyelesaian tugas', 'pct' => self::pct($tasksDone, $tasks->count()), 'target' => self::target('tuntas'), 'actual' => "{$tasksDone}/{$tasks->count()} tugas", 'target_label' => 'Target ≥ '.self::target('tuntas').'%'],
            'disposisi' => ['title' => 'Disposisi tepat waktu', 'pct' => self::pct($letters->where('state', 'on_time')->count(), $letters->count()), 'target' => self::target('disposisi'), 'actual' => $data['letters']->isEmpty() ? 'Tidak ada surat masuk' : $letters->where('state', 'on_time')->count().'/'.$letters->count().' surat', 'target_label' => 'Target ≥ '.self::target('disposisi').'%'],
            'merata' => ['title' => 'Pemerataan tugas', 'pct' => $workload['pct'], 'target' => self::target('merata'), 'actual' => $workload['zero'].' staf 0 tugas', 'target_label' => 'Target ≥ '.self::target('merata').'% ideal'],
        ];

        foreach ($donuts as $key => $donut) {
            $donuts[$key]['status'] = self::status($donut['pct'], $donut['target']);
        }

        $levels = collect([Level::Eselon3, Level::Eselon4])->map(fn (Level $level) => [
            'label' => $level->label(),
            'patuh' => $people->where('level', $level)->where('status', Compliance::Patuh)->count(),
            'total' => $people->where('level', $level)->count(),
            'deadline' => self::time($level->deadlineKey()),
        ]);

        $nonCompliant = $people->filter(fn ($p) => $p['issues'] !== []);

        return [
            'total' => $total,
            'patuh' => $patuh,
            'acted' => $acted,
            'tasks_total' => $tasks->count(),
            'tasks_done' => $tasksDone,
            'reported_people' => $reportedPeople,
            'needing' => $needing->count(),
            'reported_on_time' => $reportedOnTime,
            'donuts' => $donuts,
            'achieved' => collect($donuts)->where('status', 'good')->count(),
            'measured' => collect($donuts)->where('status', '!=', 'none')->count(),
            'levels' => $levels,
            'cascade_ok' => $levels->sum('patuh'),
            'cascade_total' => $levels->sum('total'),
            'zones' => collect(self::ZONES)->map(fn ($z, $key) => $people->where('zone', $key)->count()),
            'workload' => $workload,
            'non_compliant' => $nonCompliant,
            'non_compliant_units' => $nonCompliant->groupBy('unit')->map->count()->sortDesc(),
            'to_remind' => $nonCompliant->filter(fn ($p) => $p['user_id'] && ! $p['reminded_at'])->count(),
            'unit_tasks' => $this->unitTasks($data),
            'initiatives' => $data['initiatives']->count(),
            'initiatives_pending' => $data['initiatives']->where('status', 'pending')->count(),
        ];
    }

    /** Sebaran staf menurut jumlah tugas hari itu. */
    public function workload(Collection $people): array
    {
        $staff = $people->where('level', Level::Staff);
        $min = max(1, (int) Setting::get('workload_ideal_min'));
        $max = max($min, (int) Setting::get('workload_ideal_max'));

        $buckets = collect([
            ['label' => '0 tugas', 'from' => 0, 'to' => 0, 'status' => 'bad', 'hint' => 'belum kebagian tugas'],
            ...($min > 1 ? [['label' => $min > 2 ? '1–'.($min - 1).' tugas' : '1 tugas', 'from' => 1, 'to' => $min - 1, 'status' => 'warn', 'hint' => 'di bawah beban normal']] : []),
            ['label' => ($min === $max ? $min : "{$min}–{$max}").' tugas (ideal)', 'from' => $min, 'to' => $max, 'status' => 'good', 'hint' => 'beban ideal harian'],
            ['label' => ($max + 1).'–'.($max + 2).' tugas', 'from' => $max + 1, 'to' => $max + 2, 'status' => 'warn', 'hint' => 'padat'],
            ['label' => '≥ '.($max + 3).' tugas', 'from' => $max + 3, 'to' => PHP_INT_MAX, 'status' => 'bad', 'hint' => 'menumpuk pada orang yang sama'],
        ])->map(function (array $bucket) use ($staff) {
            $members = $staff->filter(fn ($p) => $p['tasks_total'] >= $bucket['from'] && $p['tasks_total'] <= $bucket['to']);

            return [...$bucket, 'people' => $members->count(), 'tasks' => $members->sum('tasks_total'), 'members' => $members->values()];
        });

        $peak = $buckets->max('people') ?: 1;
        $heavy = $buckets->last();
        $totalTasks = $staff->sum('tasks_total');

        return [
            'buckets' => $buckets->map(fn ($b) => [...$b, 'bar' => (int) round($b['people'] / $peak * 100)]),
            'staff' => $staff->count(),
            'zero' => $buckets->first()['people'],
            'ideal' => $buckets->firstWhere('status', 'good')['people'],
            'pct' => self::pct($buckets->firstWhere('status', 'good')['people'], $staff->count()),
            'heavy_people' => $heavy['people'],
            'heavy_share' => self::pct($heavy['tasks'], $totalTasks) ?? 0,
            'min' => $min,
            'max' => $max,
        ];
    }

    private function unitTasks(array $data): Collection
    {
        return collect($data['units'])->map(function (string $name, int $id) use ($data) {
            $tasks = $data['tasks']->where('unit_id', $id);
            $done = $tasks->where('status', TaskStatus::Selesai->value)->count();
            $pct = self::pct($done, $tasks->count());

            return ['name' => $name, 'done' => $done, 'total' => $tasks->count(), 'pct' => $pct, 'status' => self::status($pct, self::target('tuntas'))];
        })->filter(fn (array $unit, int $id) => $unit['total'] > 0 || $data['people']->where('unit_id', $id)->isNotEmpty())->values();
    }

    // ---------------------------------------------------------------------
    // Rincian indikator ("lihat sumber")
    // ---------------------------------------------------------------------

    /**
     * @return array{title:string, value:string, target:string, status:string, calc:string, source:string,
     *     groups: array<int, array{title:string, rows: array<int, array{label:string, right:string, status:string}>}>,
     *     people_title:string, people: Collection, insight:string, next: array{route:string, label:string}|null}
     */
    public function metric(string $key, array $data): array
    {
        $summary = $this->summary($data);

        return match ($key) {
            'siap' => $this->metricSiap($data, $summary),
            'lapor' => $this->metricLapor($data, $summary),
            'tuntas' => $this->metricTuntas($data, $summary),
            'disposisi' => $this->metricDisposisi($data, $summary),
            'merata' => $this->metricMerata($data, $summary),
            'lokasi' => $this->metricLokasi($data, $summary),
            'inisiatif' => $this->metricInisiatif($data),
            default => $this->metricPatuh($data, $summary),
        };
    }

    /** Baris rincian per unit: $num/$den dihitung dari pegawai unit tersebut. */
    private function unitRows(array $data, callable $num, callable $den, string $target): array
    {
        return collect($data['units'])->map(function (string $name, int $id) use ($data, $num, $den, $target) {
            $people = $data['people']->where('unit_id', $id);
            $n = $num($people, $id);
            $d = $den($people, $id);
            $pct = self::pct($n, $d);

            return ['label' => $name, 'right' => $d ? "{$n}/{$d} · {$pct}%" : '—', 'status' => self::status($pct, self::target($target)), 'pct' => $pct];
        })->filter(fn ($r) => $r['right'] !== '—')->values()->all();
    }

    private function lowest(array $rows): ?array
    {
        return collect($rows)->whereNotNull('pct')->sortBy('pct')->first();
    }

    private function person(array $p, string $right, string $status = 'bad'): array
    {
        return ['name' => $p['name'], 'sub' => $p['position'].' · '.$p['unit'], 'right' => $right, 'status' => $status, 'position_id' => $p['position_id']];
    }

    private function metricSiap(array $data, array $s): array
    {
        $people = $data['people'];
        $rows = $this->unitRows($data, fn ($p) => $p->where('status', Compliance::Patuh)->count(), fn ($p) => $p->count(), 'siap');
        $low = $this->lowest($rows);
        $late = $people->where('status', Compliance::Terlambat);
        $none = $people->where('status', Compliance::Tidak);
        $waiting = $people->where('status', Compliance::Menunggu);
        $pct = self::pct($s['patuh'], $s['total']);

        return [
            'title' => 'Kepatuhan SIAP',
            'value' => "{$s['patuh']}/{$s['total']} tepat waktu".($pct !== null ? " ({$pct}%)" : ''),
            'target' => 'Target ≥ '.self::target('siap').'% · Eselon 3 ≤ '.self::time('deadline_eselon3').', Eselon 4 ≤ '.self::time('deadline_eselon4').', Staff ≤ '.self::time('deadline_staff'),
            'status' => self::status($pct, self::target('siap')),
            'calc' => 'Jabatan yang melakukan aksi pertama hari itu (konfirmasi SIAP atas arahan atasan, mengirim/meneruskan arahan ke bawahan, atau tap SIAP harian) paling lambat batas jam jenjangnya, dibagi seluruh jabatan terisi yang dinilai.',
            'source' => 'Konfirmasi SIAP pada instruksi, arahan yang dikirim pejabat, dan tap SIAP harian beserta rekam lokasinya.',
            'groups' => [
                ['title' => 'Status '.$s['total'].' pegawai', 'rows' => [
                    ['label' => 'SIAP tepat waktu', 'right' => $s['patuh'].' orang', 'status' => 'good'],
                    ['label' => 'SIAP terlambat', 'right' => $late->count().' orang', 'status' => $late->isEmpty() ? 'good' : 'warn'],
                    ['label' => 'Belum ada aksi sampai batas jam', 'right' => $none->count().' orang', 'status' => $none->isEmpty() ? 'good' : 'bad'],
                    ...($waiting->isNotEmpty() ? [['label' => 'Menunggu (batas jam belum lewat)', 'right' => $waiting->count().' orang', 'status' => 'none']] : []),
                ]],
                ['title' => 'Rincian per unit', 'rows' => $rows],
                ['title' => 'Rincian per jenjang', 'rows' => collect(Level::compliable())->map(function (Level $level) use ($people) {
                    $group = $people->where('level', $level);
                    $pct = self::pct($group->where('status', Compliance::Patuh)->count(), $group->count());

                    return ['label' => $level->label().' (≤ '.self::time($level->deadlineKey()).')', 'right' => $group->where('status', Compliance::Patuh)->count().'/'.$group->count().($pct !== null ? " · {$pct}%" : ''), 'status' => self::status($pct, self::target('siap'))];
                })->all()],
            ],
            'people_title' => 'Belum SIAP tepat waktu',
            'people' => $none->map(fn ($p) => $this->person($p, 'Belum SIAP'))
                ->concat($late->map(fn ($p) => $this->person($p, 'Terlambat '.$p['acted_at']?->format('H.i'), 'warn')))
                ->values(),
            'insight' => $late->count() + $none->count() === 0
                ? ($waiting->isNotEmpty() ? "{$waiting->count()} pegawai masih dalam batas waktu SIAP." : 'Seluruh pegawai SIAP tepat waktu.')
                : ($late->count() + $none->count()).' pegawai belum SIAP tepat waktu ('.$none->count().' tanpa aksi, '.$late->count().' terlambat).'
                    .($low ? " Unit terendah: {$low['label']} ({$low['pct']}%)." : '').' Pegawai ini otomatis masuk daftar tidak patuh.',
            'next' => ['route' => route('kadis.compliance'), 'label' => 'Periksa & tegur di Kepatuhan Berjenjang'],
        ];
    }

    private function metricLapor(array $data, array $s): array
    {
        $needing = $data['people']->where('needs_report', '>', 0);
        $complete = fn ($p) => $p['reported'] === $p['needs_report'];
        $classes = [
            'Melapor tepat waktu & diterima atasan' => $needing->filter(fn ($p) => $complete($p) && $p['reported_on_time'] === $p['needs_report'] && $p['approved'] === $p['needs_report']),
            'Melapor tepat waktu · menunggu verifikasi atasan' => $needing->filter(fn ($p) => $complete($p) && $p['reported_on_time'] === $p['needs_report'] && $p['approved'] < $p['needs_report']),
            'Melapor terlambat' => $needing->filter(fn ($p) => $complete($p) && $p['reported_on_time'] < $p['needs_report']),
            'Sudah SIAP tetapi belum melapor lengkap' => $needing->filter(fn ($p) => ! $complete($p) && $p['status'] !== Compliance::Tidak),
            'Belum SIAP dan belum melapor lengkap' => $needing->filter(fn ($p) => ! $complete($p) && $p['status'] === Compliance::Tidak),
        ];
        $tones = ['good', 'warn', 'warn', 'bad', 'bad'];
        $rows = $this->unitRows($data, fn ($p) => $p->filter(fn ($x) => $x['needs_report'] > 0 && $x['reported_on_time'] === $x['needs_report'])->count(), fn ($p) => $p->where('needs_report', '>', 0)->count(), 'lapor');
        $low = $this->lowest($rows);
        $pct = self::pct($s['reported_on_time'], $s['needing']);
        $missing = $needing->reject($complete);
        $deadline = self::time('report_deadline');

        return [
            'title' => 'Sudah Melapor',
            'value' => "{$s['reported_on_time']}/{$s['needing']} orang tepat waktu".($pct !== null ? " ({$pct}%)" : ''),
            'target' => 'Target ≥ '.self::target('lapor')."% melapor ≤ {$deadline}",
            'status' => self::status($pct, self::target('lapor')),
            'calc' => "Pegawai yang mengirim laporan untuk seluruh instruksi wajib lapor (instruksi berisi tugas atau instruksi prioritas) paling lambat {$deadline}, dibagi pegawai yang menerima instruksi wajib lapor hari itu.",
            'source' => 'Laporan pada halaman instruksi (catatan, lampiran, titik lokasi) dan verifikasi atasan langsung.',
            'groups' => [
                ['title' => 'Status '.$needing->count().' pegawai wajib lapor', 'rows' => collect($classes)->values()->map(fn ($c, $i) => ['label' => array_keys($classes)[$i], 'right' => $c->count().' orang', 'status' => $c->isEmpty() && $i > 0 ? 'good' : $tones[$i]])->all()],
                ['title' => 'Rincian per unit', 'rows' => $rows],
            ],
            'people_title' => 'Belum melapor lengkap',
            'people' => $missing->map(fn ($p) => $this->person($p, $p['reported'].'/'.$p['needs_report'].' laporan'))->values(),
            'insight' => $missing->isEmpty()
                ? ($needing->isEmpty() ? 'Belum ada instruksi wajib lapor hari ini.' : 'Seluruh pegawai wajib lapor sudah mengirim laporan.')
                : $missing->count().' pegawai belum melapor lengkap'.($data['report_closed'] ? " sampai {$deadline}" : " (batas {$deadline} belum lewat)").'.'
                    .($low ? " Unit terendah: {$low['label']} ({$low['pct']}%)." : ''),
            'next' => ['route' => route('kadis.employees'), 'label' => 'Lihat daftar pegawai'],
        ];
    }

    private function metricTuntas(array $data, array $s): array
    {
        $tasks = $data['tasks'];
        $sources = ['duty' => 'Tugas rutin tupoksi', 'manual' => 'Tugas dari atasan (berjenjang)', 'initiative' => 'Tugas inisiatif yang disetujui'];
        $sourceRows = collect($sources)->map(function (string $label, string $source) use ($tasks) {
            $group = $tasks->where('source', $source);
            $done = $group->where('status', TaskStatus::Selesai->value)->count();
            $pct = self::pct($done, $group->count());

            return ['label' => $label, 'right' => "{$done} tuntas / {$group->count()}", 'status' => self::status($pct, self::target('tuntas'))];
        })->values()->all();
        $unitRows = collect($s['unit_tasks'])->map(fn ($u) => ['label' => $u['name'], 'right' => $u['total'] ? "{$u['done']}/{$u['total']} · {$u['pct']}%" : '—', 'status' => $u['status'], 'pct' => $u['pct']])->all();
        $low = $this->lowest($unitRows);
        $pct = self::pct($s['tasks_done'], $s['tasks_total']);
        $behind = $data['people']->filter(fn ($p) => $p['tasks_total'] > 0 && $p['tasks_done'] < $p['tasks_total'])
            ->sortBy(fn ($p) => $p['tasks_done'] / $p['tasks_total']);

        return [
            'title' => 'Penyelesaian Tugas',
            'value' => "{$s['tasks_done']}/{$s['tasks_total']} tugas".($pct !== null ? " ({$pct}%)" : ''),
            'target' => 'Target ≥ '.self::target('tuntas').'% tugas harian tuntas',
            'status' => self::status($pct, self::target('tuntas')),
            'calc' => 'Tugas berstatus Selesai dibagi seluruh tugas pada instruksi tanggal ini (gabungan tupoksi, tugas dari atasan, dan inisiatif yang disetujui).',
            'source' => 'Checklist tugas pada instruksi yang diperbarui pegawai (Proses / Tunda / Selesai).',
            'groups' => [
                ['title' => 'Sumber '.$s['tasks_total'].' tugas', 'rows' => $sourceRows],
                ['title' => 'Rincian per unit', 'rows' => $unitRows],
            ],
            'people_title' => 'Pegawai dengan tugas belum tuntas',
            'people' => $behind->map(fn ($p) => $this->person($p, $p['tasks_done'].'/'.$p['tasks_total'].' tuntas', $p['tasks_done'] === 0 ? 'bad' : 'warn'))->values(),
            'insight' => $s['tasks_total'] === 0 ? 'Belum ada tugas pada instruksi hari ini.'
                : ($s['tasks_total'] - $s['tasks_done']).' tugas belum tuntas.'.($low ? " Hambatan terbesar di {$low['label']} ({$low['pct']}%)." : ''),
            'next' => ['route' => route('kadis.employees'), 'label' => 'Pantau tugas per pegawai'],
        ];
    }

    private function metricDisposisi(array $data, array $s): array
    {
        $letters = $data['letters'];
        $measured = $letters->where('state', '!=', 'pending');
        $onTime = $measured->where('state', 'on_time')->count();
        $pct = self::pct($onTime, $measured->count());
        $hours = (int) Setting::get('disposition_hours');
        $urgent = (int) Setting::get('disposition_urgent_hours');
        $group = function (bool $isUrgent, string $label) use ($letters) {
            $g = $letters->where('urgent', $isUrgent);
            $m = $g->where('state', '!=', 'pending');
            $pct = self::pct($m->where('state', 'on_time')->count(), $m->count());

            return ['label' => $label, 'right' => $g->isEmpty() ? '0 surat' : $m->where('state', 'on_time')->count().'/'.$m->count().' tepat'.($g->where('state', 'pending')->count() ? ' · '.$g->where('state', 'pending')->count().' berjalan' : ''), 'status' => self::status($pct, self::target('disposisi'))];
        };
        $atKadis = $letters->where('stage', 'Menunggu disposisi Kepala Dinas');

        return [
            'title' => 'Disposisi Surat Tepat Waktu',
            'value' => $letters->isEmpty() ? 'Tidak ada surat masuk' : "{$onTime}/{$measured->count()} surat".($pct !== null ? " ({$pct}%)" : ''),
            'target' => 'Target ≥ '.self::target('disposisi')."% · Biasa/Rahasia ≤ {$hours} jam, Segera ≤ {$urgent} jam",
            'status' => self::status($pct, self::target('disposisi')),
            'calc' => 'Surat masuk yang diinput hari ini dan sudah didisposisi Kepala Dinas dalam batas waktu (dihitung sejak surat diinput operator), dibagi surat yang batas waktunya sudah lewat atau sudah didisposisi. Surat yang masih dalam batas waktu belum dihitung.',
            'source' => 'E-Arsip: input surat oleh Operator, paraf Kasubag Umum, dan disposisi Kepala Dinas.',
            'groups' => [
                ['title' => 'Menurut sifat surat', 'rows' => [$group(false, "Biasa / Rahasia (≤ {$hours} jam)"), $group(true, "Segera / Sangat Segera (≤ {$urgent} jam)")]],
                ['title' => 'Tahap surat', 'rows' => [
                    ['label' => 'Menunggu paraf', 'right' => $letters->where('stage', 'Menunggu paraf')->count().' surat', 'status' => 'none'],
                    ['label' => 'Menunggu disposisi Kepala Dinas', 'right' => $atKadis->count().' surat', 'status' => $atKadis->isEmpty() ? 'good' : 'warn'],
                    ['label' => 'Sudah terdisposisi', 'right' => $letters->filter(fn ($l) => str_starts_with($l['stage'], 'Terdisposisi'))->count().' surat', 'status' => 'good'],
                ]],
            ],
            'people_title' => 'Surat masuk hari ini',
            'people' => $letters->map(fn ($l) => [
                'name' => $l['agenda'].' · '.str($l['subject'])->limit(60),
                'sub' => $l['sender'].' · '.$l['nature'].' · batas '.$l['due']->format('d/m H.i'),
                'right' => $l['stage'],
                'status' => ['on_time' => 'good', 'late' => 'bad', 'pending' => 'warn'][$l['state']],
                'url' => route('arsip.show', $l['id']),
            ])->values(),
            'insight' => $letters->isEmpty() ? 'Tidak ada surat masuk yang diinput pada tanggal ini.'
                : ($atKadis->isNotEmpty() ? "{$atKadis->count()} surat menunggu disposisi Anda. " : '')
                    .($measured->where('state', 'late')->count() ? $measured->where('state', 'late')->count().' surat terlambat didisposisi.' : 'Belum ada surat yang terlambat.'),
            'next' => ['route' => route('arsip.index'), 'label' => 'Buka E-Arsip'],
        ];
    }

    private function metricMerata(array $data, array $s): array
    {
        $w = $s['workload'];

        return [
            'title' => 'Pemerataan Beban Kerja',
            'value' => $w['pct'] !== null ? "{$w['ideal']}/{$w['staff']} staf ideal ({$w['pct']}%)" : 'Belum ada staf',
            'target' => 'Target ≥ '.self::target('merata')."% staf memegang {$w['min']}–{$w['max']} tugas per hari",
            'status' => self::status($w['pct'], self::target('merata')),
            'calc' => "Persentase staf yang memegang {$w['min']}–{$w['max']} tugas (rentang ideal, bisa diatur admin) pada instruksi tanggal ini.",
            'source' => 'Pembagian tugas oleh Eselon 4 (checklist instruksi) termasuk tupoksi dan inisiatif.',
            'groups' => [
                ['title' => 'Sebaran '.$w['staff'].' staf menurut jumlah tugas', 'rows' => $w['buckets']->map(fn ($b) => ['label' => $b['label'].' · '.$b['hint'], 'right' => $b['people'].' orang'.($w['staff'] ? ' · '.self::pct($b['people'], $w['staff']).'%' : ''), 'status' => $b['people'] ? $b['status'] : 'none'])->all()],
                ['title' => 'Staf ideal per unit', 'rows' => $this->unitRows($data,
                    fn ($p) => $p->where('level', Level::Staff)->filter(fn ($x) => $x['tasks_total'] >= $w['min'] && $x['tasks_total'] <= $w['max'])->count(),
                    fn ($p) => $p->where('level', Level::Staff)->count(), 'merata')],
            ],
            'people_title' => 'Staf tanpa tugas & yang menumpuk',
            'people' => $w['buckets']->first()['members']->map(fn ($p) => $this->person($p, '0 tugas'))
                ->concat($w['buckets']->last()['members']->map(fn ($p) => $this->person($p, $p['tasks_total'].' tugas', 'warn')))->values(),
            'insight' => $w['staff'] === 0 ? 'Belum ada staf yang dinilai.'
                : ($w['heavy_people'] ? "{$w['heavy_people']} staf memegang {$w['heavy_share']}% tugas, sementara {$w['zero']} staf belum mendapat tugas." : "{$w['ideal']} dari {$w['staff']} staf berada di beban ideal; {$w['zero']} staf belum mendapat tugas.")
                    .($w['pct'] < self::target('merata') ? ' Minta Eselon 4 meratakan pembagian tugas sebelum batas jam.' : ''),
            'next' => ['route' => route('kadis.employees', ['level' => 'staff']), 'label' => 'Lihat beban per staf'],
        ];
    }

    private function metricLokasi(array $data, array $s): array
    {
        $people = $data['people'];
        $ok = $s['zones']['office'] + $s['zones']['field'];
        $outside = $people->where('zone', 'outside');
        $none = $people->where('zone', 'none');

        return [
            'title' => 'Kesesuaian Lokasi Kerja',
            'value' => "{$ok}/{$s['total']} sesuai",
            'target' => 'Target 100% di area kantor atau dinas luar yang ditetapkan atasan',
            'status' => self::status(self::pct($ok, $s['total']), 100),
            'calc' => 'Titik GPS satu kali saat pegawai menekan SIAP, memperbarui tugas, atau melapor, dibandingkan dengan radius kantor ('.Setting::get('office_radius').' m) dan penugasan dinas luar hari itu.',
            'source' => 'Rekam lokasi pada aksi pegawai (bukan pelacakan terus-menerus) dan penetapan dinas luar oleh atasan.',
            'groups' => [['title' => 'Rekap posisi '.$s['total'].' pegawai', 'rows' => collect(self::ZONES)->map(fn ($z, $key) => [
                'label' => $z[0], 'right' => $s['zones'][$key].' orang', 'status' => match ($key) {
                    'outside' => $s['zones'][$key] ? 'bad' : 'good', 'none' => $s['zones'][$key] ? 'warn' : 'good', default => 'good'
                },
            ])->values()->all()]],
            'people_title' => 'Perlu perhatian',
            'people' => $outside->map(fn ($p) => $this->person($p, 'Luar area tanpa izin'))
                ->concat($none->map(fn ($p) => $this->person($p, 'Belum ada lokasi', 'warn')))
                ->concat($people->where('zone', 'field')->map(fn ($p) => $this->person($p, 'Dinas luar'.($p['field'] ? ': '.$p['field'] : ''), 'good')))
                ->values(),
            'insight' => $outside->isEmpty() ? 'Tidak ada pegawai di luar area tanpa izin.' : "{$outside->count()} pegawai beraktivitas dari luar radius kantor tanpa penugasan dinas luar.",
            'next' => ['route' => route('kadis.map'), 'label' => 'Buka Peta Kehadiran'],
        ];
    }

    private function metricInisiatif(array $data): array
    {
        $items = $data['initiatives'];
        $labels = ['approved' => ['Disetujui atasan', 'good'], 'pending' => ['Menunggu persetujuan atasan', 'warn'], 'rejected' => ['Ditolak', 'none']];
        $byUnit = collect($data['units'])->map(fn ($name, $id) => ['label' => $name, 'right' => $items->where('unit_id', $id)->count().' usulan', 'status' => $items->where('unit_id', $id)->count() ? 'good' : 'none', 'n' => $items->where('unit_id', $id)->count()])->values();
        $top = $byUnit->sortByDesc('n')->first();
        $low = $byUnit->sortBy('n')->first();

        return [
            'title' => 'Tugas Inisiatif Pegawai',
            'value' => $items->count().' diajukan',
            'target' => 'Mengukur proaktivitas pegawai di luar perintah harian',
            'status' => $items->isEmpty() ? 'none' : 'good',
            'calc' => 'Jumlah usulan tugas inisiatif yang diajukan pegawai pada tanggal ini. Usulan yang disetujui atasan langsung menjadi pilihan checklist tugas.',
            'source' => 'Usulan inisiatif pada halaman Tupoksi pegawai dan persetujuan atasan langsung.',
            'groups' => [
                ['title' => 'Status usulan', 'rows' => collect($labels)->map(fn ($l, $key) => ['label' => $l[0], 'right' => $items->where('status', $key)->count().' usulan', 'status' => $l[1]])->values()->all()],
                ['title' => 'Per unit', 'rows' => $byUnit->all()],
            ],
            'people_title' => 'Daftar usulan',
            'people' => $items->map(fn ($i) => ['name' => $i['title'], 'sub' => $i['name'].' · '.$i['position'], 'right' => $labels[$i['status']][0] ?? $i['status'], 'status' => $labels[$i['status']][1] ?? 'none'])->values(),
            'insight' => $items->isEmpty() ? 'Belum ada usulan inisiatif pada tanggal ini.'
                : "Unit paling proaktif: {$top['label']} ({$top['n']} usulan)".($low && $low['n'] < $top['n'] ? "; paling pasif: {$low['label']} ({$low['n']})." : '.'),
            'next' => null,
        ];
    }

    private function metricPatuh(array $data, array $s): array
    {
        $list = $s['non_compliant'];
        $reasons = [
            'Belum SIAP' => fn ($i) => str_starts_with($i, 'Belum SIAP'),
            'SIAP terlambat' => fn ($i) => str_starts_with($i, 'SIAP terlambat'),
            'Belum melapor / terlambat melapor' => fn ($i) => str_contains($i, 'melapor'),
            'Di luar area tanpa izin' => fn ($i) => str_starts_with($i, 'Lokasi'),
        ];

        return [
            'title' => 'Tidak Patuh',
            'value' => $list->count().' orang'.($data['provisional'] ? ' (sementara)' : ''),
            'target' => 'Evaluasi '.self::time('verify_deadline').' · target 0 orang',
            'status' => $list->isEmpty() ? 'good' : 'bad',
            'calc' => 'Pegawai dengan minimal satu pelanggaran: belum/terlambat SIAP, belum melapor (atau melapor terlambat) sampai '.self::time('report_deadline').' padahal ada instruksi wajib lapor, atau beraktivitas di luar area tanpa izin dinas luar.'
                .($data['provisional'] ? ' Sebelum '.self::time('verify_deadline').' daftar ini masih sementara.' : ''),
            'source' => 'Gabungan kepatuhan SIAP, laporan instruksi, dan rekam lokasi hari itu.',
            'groups' => [
                ['title' => 'Menurut penyebab', 'rows' => collect($reasons)->map(fn ($match, $label) => ['label' => $label, 'right' => $list->filter(fn ($p) => collect($p['issues'])->contains($match))->count().' orang', 'status' => $list->filter(fn ($p) => collect($p['issues'])->contains($match))->isEmpty() ? 'good' : 'bad'])->values()->all()],
                ['title' => 'Per unit', 'rows' => $s['non_compliant_units']->map(fn ($n, $unit) => ['label' => $unit, 'right' => $n.' orang', 'status' => 'bad'])->values()->all()],
            ],
            'people_title' => 'Daftar pegawai tidak patuh',
            'people' => $list->map(fn ($p) => [...$this->person($p, $p['reminded_at'] ? 'Diingatkan '.$p['reminded_at'] : implode(' · ', $p['issues'])), 'issues' => $p['issues'], 'reminded_at' => $p['reminded_at']])->values(),
            'insight' => $list->isEmpty() ? 'Tidak ada pegawai tidak patuh.' : 'Unit terbanyak: '.$s['non_compliant_units']->keys()->first().' ('.$s['non_compliant_units']->first().' orang). Kirim pengingat atau tegur langsung melalui Kepatuhan Berjenjang.',
            'next' => ['route' => route('kadis.compliance'), 'label' => 'Tegur di Kepatuhan Berjenjang'],
        ];
    }

    // ---------------------------------------------------------------------
    // Pengingat
    // ---------------------------------------------------------------------

    private static function reminderKey(int $userId): string
    {
        return 'kadis-reminder:'.today()->toDateString().':'.$userId;
    }

    /** @return Collection<int, string> user_id => jam pengingat */
    private function remindedToday(Collection $userIds): Collection
    {
        if ($userIds->isEmpty()) {
            return collect();
        }

        $keys = $userIds->mapWithKeys(fn ($id) => [$id => self::reminderKey((int) $id)]);
        $values = Cache::many($keys->values()->all());

        return $keys->map(fn ($key) => $values[$key] ?? null)->filter();
    }

    /**
     * Kirim pengingat (notifikasi + WhatsApp) ke pegawai tidak patuh hari ini.
     * Tiap pegawai hanya diingatkan sekali per hari.
     *
     * @return array{sent:int, skipped:int}
     */
    public function remind(User $kadis): array
    {
        $summary = $this->summary($this->build(today()));
        $sent = 0;
        $skipped = 0;

        foreach ($summary['non_compliant'] as $person) {
            $user = $person['user_id'] ? User::query()->find($person['user_id']) : null;

            if (! $user || ! $user->is_active || ! Cache::add(self::reminderKey($user->id), now()->format('H.i'), today()->endOfDay())) {
                $skipped++;

                continue;
            }

            $user->notify((new BriefNotification(
                'Pengingat dari Kepala Dinas',
                'Mohon segera ditindaklanjuti: '.implode('; ', $person['issues']).'.',
                route('home'),
                'warning',
            ))->withWhatsApp(null, 'pengingat'));
            $sent++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }
}
