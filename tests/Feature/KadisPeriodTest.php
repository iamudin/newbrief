<?php

use App\Livewire\Kadis\Dashboard;
use App\Models\ComplianceRecord;
use App\Services\DirectionService;
use App\Services\KadisPeriodService;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-23 07:00')); // Senin
    $this->org = makeOrg();
    $this->service = app(DirectionService::class);
});

afterEach(fn () => Carbon::setTestNow());

/** Senin: alur lengkap, staf SIAP terlambat. Selasa: tidak ada aksi. Dibaca Rabu 07.10. */
function twoDays(array $org, DirectionService $service): void
{
    cascade($org, $service, 1);
    Carbon::setTestNow('2026-11-23 09:10');
    $service->checkIn($org['staff']);
    Carbon::setTestNow('2026-11-25 07:10');
}

it('evaluates past days once and stores the daily recap', function () {
    twoDays($this->org, $this->service);
    $period = app(KadisPeriodService::class);

    expect($period->evaluateDay('2026-11-23'))->toBe(3)
        ->and($period->evaluateDay('2026-11-23'))->toBe(0); // sudah dikunci

    $staff = ComplianceRecord::query()->whereDate('date', '2026-11-23')->where('user_id', $this->org['staff']->id)->first();

    expect($staff->tasks_total)->toBe(1)
        ->and($staff->needs_report)->toBe(1)
        ->and($staff->reported)->toBe(0)
        ->and($staff->issues)->toContain('Belum melapor sampai 14.30')
        ->and($staff->evaluated_at)->not->toBeNull();
});

it('builds the weekly report with trend, repeats and unit report', function () {
    twoDays($this->org, $this->service);
    $report = app(KadisPeriodService::class)->report('mingguan', today());

    expect($report['workdays'])->toBe(3)
        ->and($report['stats']['siap'])->toBe(33)          // 2 patuh dari 6 jabatan-hari yang sudah dinilai
        ->and($report['trend'])->toHaveCount(3)
        ->and($report['repeats']->pluck('user_id')->sort()->values()->all())->toBe(collect([$this->org['e4']->id, $this->org['staff']->id])->sort()->values()->all())
        ->and($report['repeats']->firstWhere('user_id', $this->org['staff']->id)['detail'])->toContain('1× SIAP terlambat')
        ->and($report['units'])->toHaveCount(1)
        ->and($report['decision'])->toContain('pembinaan');
});

it('scores B-R-I-E-F with weighted components and skips components without data', function () {
    twoDays($this->org, $this->service);
    [$from, $to] = KadisPeriodService::range('mingguan', today());
    $values = app(KadisPeriodService::class)->values($from, $to);

    // Kadis 1/2 hari (Rabu belum lewat 07.15 dikecualikan), E3 1/2, E4 1/2.
    expect($values['B']['score'])->toBe(50)
        ->and($values['B']['cause'])->toContain('Kabid')
        // Tidak ada instruksi prioritas → dua komponen F tanpa data, sisa: tugas dari atasan 0/2 tuntas.
        ->and($values['F']['parts'][0]['score'])->toBeNull()
        ->and($values['F']['score'])->toBe(0)
        ->and(KadisPeriodService::conclusion($values))->toContain('paling rendah');
});

it('counts report revisions even after approval', function () {
    cascade($this->org, $this->service, 1);
    Carbon::setTestNow('2026-11-23 10:00');
    $recipient = DirectionService::inbox($this->org['staff'])->first();
    $this->service->report($this->org['staff'], $recipient, 'Selesai');
    $this->service->verify($this->org['e4'], $recipient->fresh(), 'revision', 'Lengkapi');
    $this->service->report($this->org['staff'], $recipient->fresh(), 'Sudah dilengkapi');
    $this->service->verify($this->org['e4'], $recipient->fresh(), 'approved');

    expect($recipient->fresh()->revision_count)->toBe(1)
        ->and($recipient->fresh()->verification_status)->toBe('approved');
});

it('renders the weekly, monthly and value pages', function () {
    twoDays($this->org, $this->service);

    $this->actingAs($this->org['kadis'])->get('/kadis?periode=mingguan')->assertOk()->assertSee('Evaluasi Mingguan')->assertSee('Tidak patuh berulang')->assertSee('Tindak lanjut pimpinan');
    $this->actingAs($this->org['kadis'])->get('/kadis?periode=bulanan')->assertOk()->assertSee('Evaluasi Bulanan')->assertSee('Mgg 1');
    $this->actingAs($this->org['kadis'])->get('/kadis')->assertOk()->assertSee('Nilai BRIEF minggu ini');

    foreach (array_keys(KadisPeriodService::VALUES) as $key) {
        $this->actingAs($this->org['kadis'])->get("/kadis/nilai/{$key}?periode=bulanan")->assertOk()->assertSee('Asal skor')->assertSee('Upaya perbaikan');
    }

    $this->actingAs($this->org['kadis'])->get('/kadis/nilai/X')->assertNotFound();
    $this->actingAs($this->org['e3'])->get('/kadis/nilai/B')->assertForbidden();
});

it('moves the period anchor by week or month', function () {
    Carbon::setTestNow('2026-11-25 10:00');

    Livewire::actingAs($this->org['kadis'])->test(Dashboard::class)
        ->set('period', 'mingguan')->call('shiftDay', -1)->assertSet('date', '2026-11-18')
        ->set('period', 'bulanan')->call('shiftDay', -1)->assertSet('date', '2026-10-18')
        ->call('shiftDay', 1)->call('shiftDay', 1)->assertSet('date', '2026-11-25') // tidak melewati hari ini
        ->set('period', 'tahunan')->assertSet('period', 'harian');
});

it('evaluates the previous day in the nightly snapshot command', function () {
    twoDays($this->org, $this->service);
    $this->artisan('brief:snapshot', ['date' => '2026-11-24'])->assertSuccessful();

    expect(ComplianceRecord::query()->whereDate('date', '2026-11-24')->whereNotNull('evaluated_at')->count())->toBe(3);
});
