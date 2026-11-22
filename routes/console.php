<?php

use App\Services\ComplianceService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('brief:snapshot {date?}', function (ComplianceService $compliance) {
    $date = $this->argument('date') ?? today()->subDay()->toDateString();
    $rows = $compliance->forDate($date);
    $this->info("Kepatuhan {$date} dikunci: {$rows->count()} jabatan.");
})->purpose('Kunci (snapshot) hasil kepatuhan hari kerja sebelumnya');

Schedule::command('brief:snapshot')->dailyAt('00:10');
