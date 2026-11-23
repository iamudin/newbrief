<?php

namespace App\Livewire\Kadis;

use App\Services\KadisDashboardService;
use App\Services\KadisPeriodService;
use App\Services\WorkCalendar;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Dashboard akuntabilitas Kepala Dinas: harian, mingguan, bulanan. */
#[Layout('layouts::app')]
class Dashboard extends Component
{
    #[Url(as: 'tanggal')]
    public string $date = '';

    #[Url(as: 'periode')]
    public string $period = 'harian';

    public function mount(): void
    {
        $this->date = self::normalizeDate($this->date);
        $this->updatedPeriod();
    }

    public function updatedPeriod(): void
    {
        $this->period = array_key_exists($this->period, KadisPeriodService::PERIODS) ? $this->period : 'harian';
    }

    /** Tanggal valid, tidak melewati hari ini. */
    public static function normalizeDate(?string $date): string
    {
        try {
            $parsed = $date ? Carbon::createFromFormat('Y-m-d', $date)->startOfDay() : today();
        } catch (\Throwable) {
            $parsed = today();
        }

        return ($parsed->isAfter(today()) ? today() : $parsed)->toDateString();
    }

    public function updatedDate(): void
    {
        $this->date = self::normalizeDate($this->date);
    }

    public function shiftDay(int $direction): void
    {
        $calendar = app(WorkCalendar::class);
        $date = Carbon::parse($this->date);

        if ($this->period !== 'harian') {
            $date = $this->period === 'bulanan' ? $date->addMonthsNoOverflow($direction < 0 ? -1 : 1) : $date->addWeeks($direction < 0 ? -1 : 1);
            $this->date = self::normalizeDate($date->toDateString());

            return;
        }

        // Lompat ke hari kerja sebelumnya/berikutnya (maks. 10 hari) agar akhir pekan & libur terlewati.
        for ($i = 0; $i < 10; $i++) {
            $date->addDays($direction < 0 ? -1 : 1);
            if ($calendar->isWorkday($date) || $date->isToday()) {
                break;
            }
        }

        $this->date = self::normalizeDate($date->toDateString());
    }

    public function sendReminders(KadisDashboardService $service): void
    {
        abort_unless($this->date === today()->toDateString(), 422);

        $result = $service->remind(auth()->user());

        $this->dispatch('toast', message: $result['sent']
            ? "Pengingat dikirim ke {$result['sent']} pegawai."
            : 'Semua pegawai tidak patuh sudah diingatkan hari ini.');
    }

    public function render(KadisDashboardService $service, KadisPeriodService $periods)
    {
        $anchor = Carbon::parse($this->date);

        if ($this->period !== 'harian') {
            return view('livewire.kadis.dashboard-period', [
                'report' => $periods->report($this->period, $anchor),
                'isCurrent' => KadisPeriodService::range($this->period, $anchor)[1]->isToday(),
            ])->title('Dashboard');
        }

        $data = $service->build($anchor);
        [$from, $to] = KadisPeriodService::range('mingguan', $anchor);

        return view('livewire.kadis.dashboard', [
            'data' => $data,
            's' => $service->summary($data),
            'values' => $periods->values($from, $to),
        ])->title('Dashboard');
    }
}
