<?php

namespace App\Livewire\Kadis;

use App\Services\KadisDashboardService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Rincian satu indikator dashboard: rumus, sumber data, rincian per unit, kesimpulan & tindak lanjut. */
#[Layout('layouts::app')]
class Metric extends Component
{
    #[Locked]
    public string $metric = 'siap';

    #[Url(as: 'tanggal')]
    public string $date = '';

    public function mount(string $metric): void
    {
        abort_unless(array_key_exists($metric, KadisDashboardService::METRICS), 404);
        $this->metric = $metric;
        $this->date = Dashboard::normalizeDate($this->date);
    }

    public function sendReminders(KadisDashboardService $service): void
    {
        abort_unless($this->metric === 'patuh' && $this->date === today()->toDateString(), 422);

        $result = $service->remind(auth()->user());

        $this->dispatch('toast', message: $result['sent']
            ? "Pengingat dikirim ke {$result['sent']} pegawai."
            : 'Semua pegawai tidak patuh sudah diingatkan hari ini.');
    }

    public function render(KadisDashboardService $service)
    {
        $data = $service->build($this->date);
        $detail = $service->metric($this->metric, $data);

        return view('livewire.kadis.metric', [
            'data' => $data,
            'detail' => $detail,
            'toRemind' => $this->metric === 'patuh' ? $detail['people']->filter(fn ($p) => ! $p['reminded_at'])->count() : 0,
        ])->title($detail['title']);
    }
}
