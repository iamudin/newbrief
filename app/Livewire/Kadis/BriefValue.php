<?php

namespace App\Livewire\Kadis;

use App\Services\KadisPeriodService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Rincian satu nilai BRIEF: makna, rumus berbobot, penyebab, upaya perbaikan & tindak lanjut. */
#[Layout('layouts::app')]
class BriefValue extends Component
{
    #[Locked]
    public string $value = 'B';

    #[Url(as: 'tanggal')]
    public string $date = '';

    #[Url(as: 'periode')]
    public string $period = 'mingguan';

    public function mount(string $value): void
    {
        abort_unless(array_key_exists($value, KadisPeriodService::VALUES), 404);
        $this->value = $value;
        $this->date = Dashboard::normalizeDate($this->date);
        $this->period = $this->period === 'bulanan' ? 'bulanan' : 'mingguan';
    }

    public function render(KadisPeriodService $periods)
    {
        [$from, $to] = KadisPeriodService::range($this->period, Carbon::parse($this->date));
        $values = $periods->values($from, $to);

        return view('livewire.kadis.brief-value', [
            'values' => $values,
            'v' => $values[$this->value],
            'label' => KadisPeriodService::label($this->period, $from, $to),
            'isCurrent' => $to->isToday(),
        ])->title('Nilai '.KadisPeriodService::VALUES[$this->value]);
    }
}
