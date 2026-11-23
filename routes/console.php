<?php

use App\Services\ComplianceService;
use App\Services\DirectionReminderService;
use App\Services\KadisPeriodService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('brief:snapshot {date?}', function (ComplianceService $compliance, KadisPeriodService $period) {
    $date = $this->argument('date') ?? today()->subDay()->toDateString();
    $rows = $compliance->forDate($date);
    $period->evaluateDay($date); // rekap evaluasi harian untuk dashboard mingguan/bulanan
    $this->info("Kepatuhan {$date} dikunci: {$rows->count()} jabatan.");
})->purpose('Kunci (snapshot) hasil kepatuhan hari kerja sebelumnya');

Schedule::command('brief:snapshot')->dailyAt('00:10');

Artisan::command('brief:remind-directions', function (DirectionReminderService $reminders) {
    $count = $reminders->run();
    $this->info("Pengingat instruksi dikirim ke {$count} pejabat.");
})->purpose('Ingatkan (WhatsApp) pejabat yang belum membuat instruksi ke bawahan sampai batas jamnya');

Schedule::command('brief:remind-directions')->everyMinute()->weekdays()->between('06:00', '17:00')->withoutOverlapping();
