<?php

use App\Enums\TaskStatus;
use App\Livewire\Admin\Settings;
use App\Livewire\Kadis\Dashboard;
use App\Livewire\Kadis\Metric;
use App\Models\Letter;
use App\Models\Setting;
use App\Models\User;
use App\Services\DirectionService;
use App\Services\KadisDashboardService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-23 07:00')); // Senin
    $this->org = makeOrg();
    $this->service = app(DirectionService::class);
});

afterEach(fn () => Carbon::setTestNow());

/** Kadis → E3 (arahan), E3 → E4, E4 → staff dengan $items tugas. */
function cascade(array $org, DirectionService $service, int $items = 2): void
{
    $service->send($org['kadis'], 'general', 'Arahan pagi', [['position_id' => $org['positions']['e3']->id]]);
    Carbon::setTestNow('2026-11-23 07:40');
    $service->confirm($org['e3'], DirectionService::inbox($org['e3'])->first());
    $service->send($org['e3'], 'task', 'Teruskan', [['position_id' => $org['positions']['e4']->id, 'items' => [['title' => 'Koordinasi']]]]);
    Carbon::setTestNow('2026-11-23 08:10');
    $service->confirm($org['e4'], DirectionService::inbox($org['e4'])->first());
    $service->send($org['e4'], 'task', 'Kerjakan', [['position_id' => $org['positions']['staff']->id, 'items' => collect(range(1, $items))->map(fn ($i) => ['title' => "Tugas {$i}"])->all()]]);
}

it('summarises the daily cascade, tasks and workload from real data', function () {
    cascade($this->org, $this->service, 2);
    Carbon::setTestNow('2026-11-23 08:40');
    $staffInbox = DirectionService::inbox($this->org['staff'])->first();
    $this->service->confirm($this->org['staff'], $staffInbox);
    $this->service->updateTask($this->org['staff'], $staffInbox->tasks->first(), TaskStatus::Selesai->value);

    $service = app(KadisDashboardService::class);
    $data = $service->build(today());
    $s = $service->summary($data);

    expect($s['total'])->toBe(3)
        ->and($s['patuh'])->toBe(3)
        ->and($s['cascade_ok'])->toBe(2)
        ->and($s['tasks_total'])->toBe(3)
        ->and($s['tasks_done'])->toBe(1)
        ->and($s['donuts']['siap']['pct'])->toBe(100)
        ->and($s['donuts']['siap']['status'])->toBe('good')
        ->and($s['donuts']['tuntas']['pct'])->toBe(33)
        ->and($s['donuts']['tuntas']['status'])->toBe('bad')
        ->and($s['workload']['ideal'])->toBe(1)
        ->and($s['workload']['pct'])->toBe(100)
        ->and($data['kadis']['sent_at']->format('H.i'))->toBe('07.00')
        ->and($data['kadis']['on_time'])->toBeTrue()
        ->and($s['non_compliant'])->toBeEmpty();
});

it('flags late SIAP, missing reports after the report deadline and outside locations', function () {
    cascade($this->org, $this->service, 1);
    Carbon::setTestNow('2026-11-23 09:10');
    $office = ['latitude' => (float) Setting::get('office_latitude') + 0.05, 'longitude' => (float) Setting::get('office_longitude')];
    $this->service->checkIn($this->org['staff'], $office); // terlambat & di luar area

    Carbon::setTestNow('2026-11-23 15:30');
    $s = app(KadisDashboardService::class)->summary(app(KadisDashboardService::class)->build(today()));
    $issues = $s['non_compliant']->firstWhere('user_id', $this->org['staff']->id)['issues'];

    expect($issues)->toHaveCount(3)
        ->and($issues[0])->toStartWith('SIAP terlambat 09.10')
        ->and($issues[1])->toStartWith('Belum melapor sampai 14.30')
        ->and($issues[2])->toStartWith('Lokasi di luar area')
        ->and($s['zones']['outside'])->toBe(1)
        // Eselon 4 juga belum melapor tugas dari Eselon 3.
        ->and($s['non_compliant']->firstWhere('user_id', $this->org['e4']->id)['issues'])->toContain('Belum melapor sampai 14.30');
});

it('does not flag missing reports before the report deadline', function () {
    cascade($this->org, $this->service, 1);
    Carbon::setTestNow('2026-11-23 10:00');
    $s = app(KadisDashboardService::class)->summary(app(KadisDashboardService::class)->build(today()));

    // Staf belum SIAP (lewat 08.45), tapi laporan belum dinilai.
    expect($s['non_compliant']->pluck('issues')->flatten()->filter(fn ($i) => str_contains($i, 'melapor')))->toBeEmpty()
        ->and($s['non_compliant']->firstWhere('user_id', $this->org['staff']->id)['issues'])->toBe(['Belum SIAP (batas 08.45)']);
});

it('measures letter disposition timeliness by nature', function () {
    $make = fn (string $nature, ?string $disposedAt) => Letter::query()->create([
        'agenda_number' => uniqid(), 'letter_number' => '1', 'letter_date' => today(), 'received_date' => today(),
        'sender' => 'Setda', 'subject' => 'Surat', 'nature' => $nature, 'file_path' => 'x.pdf', 'file_name' => 'x.pdf',
        'created_by' => $this->org['kadis']->id, 'operator_position_id' => $this->org['positions']['e4']->id,
        'paraf_at' => $disposedAt, 'disposed_at' => $disposedAt,
    ]);
    $make('segera', '2026-11-23 10:00');  // 3 jam → tepat (≤ 4 jam)
    $make('segera', '2026-11-23 12:00');  // 5 jam → terlambat
    $make('biasa', null);                 // masih dalam 24 jam → belum dihitung

    $s = app(KadisDashboardService::class)->summary(app(KadisDashboardService::class)->build(today()));

    expect($s['donuts']['disposisi']['pct'])->toBe(50)
        ->and($s['donuts']['disposisi']['actual'])->toBe('1/2 surat');
});

it('renders the dashboard and every metric page for the kadis', function () {
    cascade($this->org, $this->service);
    Carbon::setTestNow('2026-11-23 15:30');

    $this->actingAs($this->org['kadis'])->get('/kadis')->assertOk()
        ->assertSee('Dashboard Akuntabilitas')->assertSee('Evaluasi kinerja kantor')->assertSee('Peta beban kerja');

    foreach (array_keys(KadisDashboardService::METRICS) as $key) {
        $this->actingAs($this->org['kadis'])->get("/kadis/indikator/{$key}")->assertOk()->assertSee('Cara menghitung');
    }

    $this->actingAs($this->org['kadis'])->get('/kadis/indikator/rahasia')->assertNotFound();
    $this->actingAs($this->org['staff'])->get('/kadis/indikator/siap')->assertForbidden();
    $this->actingAs($this->org['kadis'])->get('/kadis?tanggal=2026-11-22')->assertOk()->assertSee('akhir pekan');
});

it('sends one reminder per non-compliant employee per day', function () {
    cascade($this->org, $this->service, 1);
    Carbon::setTestNow('2026-11-23 15:30');

    Livewire::actingAs($this->org['kadis'])->test(Dashboard::class)->call('sendReminders');
    $count = DatabaseNotification::query()->where('data->title', 'Pengingat dari Kepala Dinas')->count();

    Livewire::actingAs($this->org['kadis'])->test(Metric::class, ['metric' => 'patuh'])->call('sendReminders')->assertSee('Diingatkan');

    expect($count)->toBeGreaterThan(0)
        ->and(DatabaseNotification::query()->where('data->title', 'Pengingat dari Kepala Dinas')->count())->toBe($count)
        ->and(DatabaseNotification::query()->where('notifiable_id', $this->org['staff']->id)->where('data->title', 'Pengingat dari Kepala Dinas')->exists())->toBeTrue();
});

it('refuses reminders for past dates', function () {
    Livewire::actingAs($this->org['kadis'])->test(Dashboard::class)->set('date', '2026-11-20')->call('sendReminders')->assertStatus(422);
});

it('clamps future dates to today', function () {
    Livewire::actingAs($this->org['kadis'])->test(Dashboard::class)->set('date', '2027-01-01')->assertSet('date', '2026-11-23');
});

it('lets the admin tune dashboard targets without touching whatsapp settings', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Setting::put('wa_host', 'https://wa.example.test');
    $component = Livewire::actingAs($admin)->test(Settings::class);
    Setting::put('wa_host', 'https://wa-baru.example.test'); // diubah di halaman WhatsApp setelah form dibuka

    $component->set('values.target_siap', '90')->set('values.workload_ideal_max', '1')->call('save')
        ->assertHasErrors('values.workload_ideal_max');

    $component->set('values.workload_ideal_max', '4')->call('save')->assertHasNoErrors();

    expect(KadisDashboardService::target('siap'))->toBe(90)
        ->and(Setting::get('workload_ideal_max'))->toBe('4')
        ->and(Setting::get('wa_host'))->toBe('https://wa-baru.example.test');
});
