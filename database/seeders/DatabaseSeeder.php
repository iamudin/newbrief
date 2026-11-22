<?php

namespace Database\Seeders;

use App\Enums\Level;
use App\Enums\TaskStatus;
use App\Models\Duty;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\Setting;
use App\Models\User;
use App\Services\DirectionService;
use App\Services\WorkCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Data contoh BRIEF. Semua akun demo memakai password: password
 * Login memakai NIP, contoh: admin (super admin), 197001012000011001 (Kepala Dinas).
 */
class DatabaseSeeder extends Seeder
{
    private int $nipCounter = 1000;

    private string $password;

    public function run(): void
    {
        $this->password = Hash::make('password');

        foreach (Setting::DEFAULTS as $key => $value) {
            Setting::put($key, $value);
        }

        User::query()->create([
            'name' => 'Super Admin',
            'nip' => 'admin',
            'password' => $this->password,
            'is_admin' => true,
        ]);

        $structure = [
            'name' => 'Kepala Dinas', 'level' => 'kadis', 'category' => 'kadis', 'holder' => 'Dr. H. Ahmad Fauzi, M.Si',
            'children' => [
                ['name' => 'Sekretaris', 'level' => 'eselon3', 'category' => 'sekretaris', 'unit' => 'Sekretariat', 'holder' => 'Ir. Siti Rahmawati, M.M', 'children' => [
                    ['name' => 'Kasubag Umum & Kepegawaian', 'level' => 'eselon4', 'category' => 'kasubag', 'unit' => 'Sekretariat', 'holder' => 'Budi Santoso, S.E', 'children' => [
                        ['name' => 'Satpam Pos Depan', 'level' => 'staff', 'category' => 'satpam', 'holder' => 'Joko Prasetyo'],
                        ['name' => 'Satpam Pos Belakang', 'level' => 'staff', 'category' => 'satpam', 'holder' => 'Agus Salim'],
                        ['name' => 'Cleaning Service Gedung A', 'level' => 'staff', 'category' => 'cs', 'holder' => 'Sri Wahyuni'],
                        ['name' => 'Pengadministrasi Umum', 'level' => 'staff', 'category' => 'administrasi', 'holder' => 'Dewi Lestari, A.Md'],
                    ]],
                    ['name' => 'Kasubag Keuangan', 'level' => 'eselon4', 'category' => 'kasubag', 'unit' => 'Sekretariat', 'holder' => 'Rina Marlina, S.Ak', 'children' => [
                        ['name' => 'Operator Keuangan', 'level' => 'staff', 'category' => 'operator', 'holder' => 'Fajar Nugroho, S.Kom'],
                    ]],
                ]],
                ['name' => 'Kepala Bidang Pelayanan', 'level' => 'eselon3', 'category' => 'kabid', 'unit' => 'Bidang Pelayanan', 'holder' => 'Drs. Hendra Gunawan', 'previous' => 'Drs. Bambang Wijaya (pensiun)', 'children' => [
                    ['name' => 'Kasi Pelayanan Perizinan', 'level' => 'eselon4', 'category' => 'kasi', 'unit' => 'Bidang Pelayanan', 'holder' => 'Maya Sari, S.H', 'children' => [
                        ['name' => 'Operator Perizinan', 'level' => 'staff', 'category' => 'operator', 'holder' => 'Andi Pratama'],
                        ['name' => 'Petugas Front Office', 'level' => 'staff', 'category' => 'administrasi', 'holder' => 'Nur Aisyah'],
                    ]],
                    ['name' => 'JF Analis Kebijakan Ahli Muda', 'level' => 'eselon4', 'category' => 'jafung', 'unit' => 'Bidang Pelayanan', 'holder' => 'Yusuf Hidayat, S.IP'],
                ]],
                ['name' => 'Kepala Bidang Penanaman Modal', 'level' => 'eselon3', 'category' => 'kabid', 'unit' => 'Bidang Penanaman Modal', 'holder' => 'Ir. Lukman Hakim, M.T', 'children' => [
                    ['name' => 'Kasi Promosi Investasi', 'level' => 'eselon4', 'category' => 'kasi', 'unit' => 'Bidang Penanaman Modal', 'holder' => 'Indah Permata, S.E', 'children' => [
                        ['name' => 'Operator Data Investasi', 'level' => 'staff', 'category' => 'operator', 'holder' => 'Rizky Ramadhan'],
                    ]],
                ]],
            ],
        ];

        $this->createPosition($structure, null, 0);

        $this->seedHistory();
    }

    /** @param array<string, mixed> $node */
    private function createPosition(array $node, ?Position $parent, int $order): void
    {
        $position = Position::query()->create([
            'parent_id' => $parent?->id,
            'name' => $node['name'],
            'level' => $node['level'],
            'category' => $node['category'],
            'unit' => $node['unit'] ?? $parent?->unit,
            'sort_order' => $order,
        ]);

        foreach ($this->dutiesFor($node['category']) as $i => $title) {
            Duty::query()->create(['position_id' => $position->id, 'title' => $title, 'sort_order' => $i]);
        }

        $start = today()->subMonths(2)->startOfMonth();

        if (isset($node['previous'])) {
            $previous = $this->makeUser($node['previous']);
            PositionAssignment::query()->create(['position_id' => $position->id, 'user_id' => $previous->id, 'started_at' => today()->subYears(3), 'ended_at' => $start->copy()->subDay()]);
            $previous->update(['is_active' => false]);
        }

        $holder = $this->makeUser($node['holder'], $node['level'] === 'kadis' ? '197001012000011001' : null);
        PositionAssignment::query()->create(['position_id' => $position->id, 'user_id' => $holder->id, 'started_at' => $start]);

        foreach ($node['children'] ?? [] as $i => $child) {
            $this->createPosition($child, $position, $i);
        }
    }

    private function makeUser(string $name, ?string $nip = null): User
    {
        $this->nipCounter++;

        return User::query()->create([
            'name' => $name,
            'nip' => $nip ?? '1985'.str_pad((string) $this->nipCounter, 14, '0', STR_PAD_LEFT),
            'password' => $this->password,
        ]);
    }

    /** @return array<int, string> */
    private function dutiesFor(string $category): array
    {
        return match ($category) {
            'kadis' => ['Menetapkan kebijakan teknis dinas', 'Mengendalikan pelaksanaan program', 'Membina dan mengevaluasi kinerja pegawai'],
            'sekretaris' => ['Mengoordinasikan penyusunan program dan anggaran', 'Mengelola urusan umum, kepegawaian, dan keuangan', 'Menyusun laporan kinerja dinas'],
            'kabid' => ['Menyusun rencana kerja bidang', 'Mengoordinasikan pelaksanaan kegiatan bidang', 'Mengevaluasi capaian kinerja bidang'],
            'kasubag' => ['Menyusun rencana kegiatan subbagian', 'Membagi tugas dan memverifikasi laporan staf', 'Mengelola administrasi dan arsip subbagian', 'Menyusun laporan bulanan subbagian'],
            'kasi' => ['Menyusun rencana kegiatan seksi', 'Memantau pelayanan dan SLA', 'Memverifikasi laporan staf seksi', 'Menyusun laporan capaian seksi'],
            'jafung' => ['Menganalisis kebijakan perizinan', 'Menyusun rekomendasi kebijakan', 'Menyusun kajian evaluasi layanan'],
            'satpam' => ['Patroli keliling area kantor', 'Mengatur dan mencatat tamu', 'Memeriksa kunci dan pintu akses', 'Mengawasi area parkir'],
            'cs' => ['Membersihkan ruang pelayanan', 'Membersihkan toilet dan pantry', 'Membuang sampah dan memilah limbah', 'Merapikan ruang rapat'],
            'administrasi' => ['Mengagendakan surat masuk dan keluar', 'Mengarsipkan dokumen', 'Menyiapkan bahan rapat', 'Menginput data kepegawaian'],
            'operator' => ['Menginput data ke aplikasi', 'Memverifikasi kelengkapan berkas', 'Membuat rekap harian', 'Memperbarui basis data'],
            default => [],
        };
    }

    /** Riwayat 10 hari kerja terakhir + hari ini, agar dashboard terisi. */
    private function seedHistory(): void
    {
        $service = app(DirectionService::class);
        $calendar = app(WorkCalendar::class);
        $days = array_slice($calendar->workdays(today()->subDays(16), today()->subDay()), -10);
        $office = ['latitude' => (float) Setting::get('office_latitude'), 'longitude' => (float) Setting::get('office_longitude'), 'accuracy' => 15];
        mt_srand(42);

        foreach ([...$days, today()->toDateString()] as $day) {
            $isToday = $day === today()->toDateString();
            $at = fn (string $time) => Carbon::parse($day.' '.$time)->addSeconds(mt_rand(0, 59));
            $kadis = Position::query()->level(Level::Kadis)->first();
            $kadisUser = $kadis->holder();

            Carbon::setTestNow($at('07:0'.mt_rand(0, 9)));
            $general = $service->send($kadisUser, 'general', 'Pastikan layanan perizinan berjalan sesuai SLA, tidak ada berkas tertunda lebih dari 3 hari. Laporkan kendala sebelum pukul 14.30.', $kadis->children->map(fn ($p) => ['position_id' => $p->id])->all(), 'Arahan Umum Harian');
            $pelayanan = $kadis->children->firstWhere('category', 'kabid');
            $service->send($kadisUser, 'priority', 'Siapkan bahan paparan capaian investasi triwulan untuk rapat pimpinan besok.', [['position_id' => $pelayanan->id]], 'Prioritas: Paparan Triwulan');

            foreach ($kadis->children as $e3) {
                $e3User = $e3->holder();
                $late = mt_rand(1, 100) <= 20;
                Carbon::setTestNow($at($late ? '08:1'.mt_rand(0, 9) : '07:'.mt_rand(20, 55)));

                foreach ($service::inbox($e3User)->whereHas('direction', fn ($q) => $q->whereDate('date', $day))->get() as $r) {
                    $service->confirm($e3User, $r, $office);
                }

                $items = $e3->children->map(fn ($e4) => ['position_id' => $e4->id, 'items' => [
                    ['title' => 'Tindak lanjut arahan umum Kepala Dinas', 'source' => 'manual'],
                    ['source' => 'duty', 'duty_id' => $e4->duties->first()?->id, 'title' => ''],
                ]])->all();
                $service->send($e3User, 'task', 'Teruskan arahan Kepala Dinas ke tim masing-masing dan pastikan laporan masuk tepat waktu.', $items, null, $general->id, null, $office);
            }

            foreach (Position::query()->level(Level::Eselon4)->get() as $e4) {
                $e4User = $e4->holder();
                $late = mt_rand(1, 100) <= 25;
                Carbon::setTestNow($at($late ? '08:4'.mt_rand(0, 9) : '08:'.mt_rand(10, 29)));
                $incoming = $service::inbox($e4User)->whereHas('direction', fn ($q) => $q->whereDate('date', $day))->first();

                if ($incoming) {
                    $service->confirm($e4User, $incoming, $office);
                }

                if ($e4->children->isNotEmpty()) {
                    $items = $e4->children->map(fn ($s) => ['position_id' => $s->id, 'items' => $s->duties->take(mt_rand(2, 3))->map(fn ($d) => ['source' => 'duty', 'duty_id' => $d->id, 'title' => ''])->push(['title' => 'Laporkan kendala layanan hari ini', 'source' => 'manual'])->all()])->all();
                    $service->send($e4User, 'task', 'Kerjakan checklist berikut dan kirim laporan sebelum 14.30.', $items, null, $incoming?->direction_id, null, $office);
                } elseif ($incoming === null) {
                    $service->checkIn($e4User, $office);
                }
            }

            foreach (Position::query()->level(Level::Staff)->get() as $staff) {
                $user = $staff->holder();
                $roll = mt_rand(1, 100);

                if ($roll <= 8) {
                    continue; // tidak SIAP
                }

                $coords = mt_rand(1, 100) <= 85 ? $office : ['latitude' => $office['latitude'] + 0.02, 'longitude' => $office['longitude'] + 0.015, 'accuracy' => 30];
                Carbon::setTestNow($at($roll <= 20 ? '08:5'.mt_rand(0, 9) : '08:'.mt_rand(15, 44)));
                $service->checkIn($user, $coords);

                if ($isToday) {
                    continue;
                }

                $recipient = $service::inbox($user)->whereHas('direction', fn ($q) => $q->whereDate('date', $day))->with('tasks')->first();

                if (! $recipient) {
                    continue;
                }

                Carbon::setTestNow($at('1'.mt_rand(0, 3).':'.mt_rand(10, 59)));

                foreach ($recipient->tasks as $task) {
                    $status = mt_rand(1, 100) <= 80 ? TaskStatus::Selesai : (mt_rand(0, 1) ? TaskStatus::Proses : TaskStatus::Tunda);
                    $service->updateTask($user, $task, $status->value, null, null, $coords);
                }

                $service->report($user, $recipient->fresh('tasks', 'direction'), 'Pekerjaan hari ini telah dilaksanakan.', null, $coords);

                Carbon::setTestNow($at('14:'.mt_rand(31, 59)));
                $service->verify($recipient->direction->fromPosition->holder(), $recipient->fresh(), mt_rand(1, 100) <= 85 ? 'approved' : 'revision', null);
            }
        }

        Carbon::setTestNow();
    }
}
