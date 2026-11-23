<?php

namespace App\Livewire\Kadis;

use App\Services\KadisDashboardService;
use App\Services\WorkCalendar;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Dashboard akuntabilitas harian Kepala Dinas. */
#[Layout('layouts::app')]
class Dashboard extends Component
{
    #[Url(as: 'tanggal')]
    public string $date = '';

    public function mount(): void
    {
        $this->date = self::normalizeDate($this->date);
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

    public function render(KadisDashboardService $service)
    {
        $data = $service->build($this->date);

        return view('livewire.kadis.dashboard', [
            'data' => $data,
            's' => $service->summary($data),
        ])->title('Dashboard');
    }
}
