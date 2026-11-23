<?php

namespace App\Services;

use App\Enums\Level;
use App\Models\Direction;
use App\Models\Position;
use App\Models\Setting;
use App\Notifications\BriefNotification;
use Illuminate\Support\Facades\Cache;

/**
 * Pengingat WhatsApp untuk pejabat (Eselon 3 / Eselon 4) yang belum membuat
 * instruksi ke bawahan sampai batas jam levelnya. Dikirim sekali per hari per jenjang.
 */
class DirectionReminderService
{
    public function __construct(private WorkCalendar $calendar) {}

    /** Jalankan untuk semua jenjang yang batas jamnya sudah lewat. Mengembalikan jumlah pejabat yang diingatkan. */
    public function run(): int
    {
        if (Setting::get('wa_remind_directions') !== '1' || ! $this->calendar->isWorkday(today())) {
            return 0;
        }

        $total = 0;

        foreach ([Level::Eselon3, Level::Eselon4] as $level) {
            $deadline = today()->setTimeFromTimeString(ComplianceService::deadline($level));
            $end = today()->setTimeFromTimeString(Setting::get('workday_end'));

            if (now()->lt($deadline) || now()->gt($end)) {
                continue;
            }

            // Sekali per hari per jenjang.
            if (! Cache::add('brief:remind-directions:'.today()->toDateString().':'.$level->value, true, now()->endOfDay())) {
                continue;
            }

            $total += $this->remindLevel($level);
        }

        return $total;
    }

    public function remindLevel(Level $level): int
    {
        $deadline = ComplianceService::deadline($level);
        $sentToday = Direction::query()->whereDate('date', today())->pluck('from_position_id')->unique();

        $positions = Position::query()
            ->level($level)
            ->with('currentAssignment.user')
            ->whereHas('children', fn ($q) => $q->where('level', $level->child()->value))
            ->whereNotIn('id', $sentToday)
            ->get();

        $count = 0;

        foreach ($positions as $position) {
            $holder = $position->holder();

            if (! $holder) {
                continue;
            }

            $holder->notify((new BriefNotification(
                'Pengingat: instruksi belum diteruskan',
                "Batas membuat/meneruskan instruksi ke bawahan pukul {$deadline} telah lewat dan Anda ({$position->name}) belum membuat instruksi hari ini. Mohon segera ditindaklanjuti.",
                $level === Level::Eselon3 ? route('eselon3.directions') : route('eselon4.directions'),
                'warning',
            ))->withWhatsApp(null, 'pengingat'));

            $count++;
        }

        return $count;
    }
}
