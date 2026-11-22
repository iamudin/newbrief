<?php

use App\Enums\Compliance;
use App\Models\ComplianceRecord;
use App\Models\Setting;
use App\Services\ComplianceService;
use App\Services\DirectionService;
use App\Services\LocationService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-23 07:00')); // Senin
});

afterEach(fn () => Carbon::setTestNow());

it('rates eselon 3 on time when confirming before 08:00', function () {
    $org = makeOrg();
    $service = app(DirectionService::class);
    $service->send($org['kadis'], 'general', 'Arahan', [['position_id' => $org['positions']['e3']->id]]);

    Carbon::setTestNow('2026-11-23 07:45');
    $service->confirm($org['e3'], DirectionService::inbox($org['e3'])->first());

    expect(app(ComplianceService::class)->today($org['positions']['e3'])['status'])->toBe(Compliance::Patuh);
});

it('counts creating a direction as compliant even without a kadis direction', function () {
    $org = makeOrg();
    Carbon::setTestNow('2026-11-23 07:50');
    app(DirectionService::class)->send($org['e3'], 'task', 'Inisiatif sendiri', [['position_id' => $org['positions']['e4']->id, 'items' => [['title' => 'a']]]]);

    expect(app(ComplianceService::class)->today($org['positions']['e3'])['status'])->toBe(Compliance::Patuh);
});

it('rates late and missing actions', function () {
    $org = makeOrg();
    $compliance = app(ComplianceService::class);

    expect($compliance->today($org['positions']['staff'])['status'])->toBe(Compliance::Menunggu);

    Carbon::setTestNow('2026-11-23 08:50');
    app(DirectionService::class)->checkIn($org['staff']);

    expect($compliance->today($org['positions']['staff'])['status'])->toBe(Compliance::Terlambat)
        ->and($compliance->today($org['positions']['e4'])['status'])->toBe(Compliance::Tidak)
        ->and($compliance->today($org['positions']['kadis']))->toBeNull();
});

it('snapshots past days and skips weekends', function () {
    $org = makeOrg();
    $compliance = app(ComplianceService::class);

    Carbon::setTestNow('2026-11-24 09:00');
    $rows = $compliance->forDate('2026-11-23');

    expect($rows)->toHaveCount(3)
        ->and(ComplianceRecord::query()->count())->toBe(3)
        ->and($compliance->forDate('2026-11-22'))->toBeEmpty();
});

it('records the location zone relative to the office radius and field duty', function () {
    $org = makeOrg();
    $service = app(DirectionService::class);
    $office = ['latitude' => (float) Setting::get('office_latitude'), 'longitude' => (float) Setting::get('office_longitude')];

    expect($service->checkIn($org['staff'], $office)->location_zone)->toBe('office');

    $far = ['latitude' => $office['latitude'] + 0.05, 'longitude' => $office['longitude']];
    expect(app(LocationService::class)->record($org['e4'], $far, 'task')->zone)->toBe('outside');

    $service->setFieldDuty($org['e3'], $org['positions']['e4'], true, 'Kantor Gubernur');
    expect(app(LocationService::class)->record($org['e4'], $far, 'task')->zone)->toBe('field');
});
