<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

class WorkCalendar
{
    public function isWorkday(Carbon|string $date): bool
    {
        $date = Carbon::parse($date);

        return ! $date->isWeekend() && ! Holiday::query()->whereDate('date', $date->toDateString())->exists();
    }

    /** @return array<int, string> */
    public function workdays(Carbon $from, Carbon $to): array
    {
        $holidays = Holiday::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->all();

        return collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()))
            ->reject(fn (CarbonInterface $d) => $d->isWeekend() || in_array($d->toDateString(), $holidays, true))
            ->map(fn (CarbonInterface $d) => $d->toDateString())
            ->values()
            ->all();
    }

    /**
     * Rentang tanggal untuk filter dashboard.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function range(string $period): array
    {
        $today = today();

        return match ($period) {
            'today' => [$today->copy(), $today->copy()],
            'month' => [$today->copy()->startOfMonth(), $today->copy()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            default => [$today->copy()->startOfWeek(), $today->copy()],
        };
    }

    /** @return array<string, string> */
    public static function periods(): array
    {
        return ['today' => 'Hari ini', 'week' => 'Minggu ini', 'month' => 'Bulan ini', 'last_month' => 'Bulan lalu'];
    }
}
