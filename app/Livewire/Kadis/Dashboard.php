<?php

namespace App\Livewire\Kadis;

use App\Enums\Compliance;
use App\Enums\Level;
use App\Models\Direction;
use App\Models\DirectionRecipient;
use App\Models\Initiative;
use App\Models\Position;
use App\Services\ComplianceService;
use App\Services\WorkCalendar;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::app')]
class Dashboard extends Component
{
    #[Url]
    public string $period = 'week';

    public function render(ComplianceService $compliance)
    {
        [$from, $to] = WorkCalendar::range($this->period);
        $kadis = auth()->user()->position();

        // Arahan Kadis pada periode & konfirmasi SIAP Eselon 3
        $directions = Direction::query()
            ->where('from_position_id', $kadis->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString().' 23:59:59'])
            ->with(['recipients.toPosition', 'recipients.toUser'])
            ->latest('date')->latest('id')
            ->get();

        $kadisRecipients = $directions->flatMap->recipients;

        // Semua penerima instruksi (seluruh jenjang) pada periode
        $allRecipients = DirectionRecipient::query()
            ->whereHas('direction', fn ($q) => $q->whereBetween('date', [$from->toDateString(), $to->toDateString().' 23:59:59']))
            ->get(['id', 'confirmed_at', 'reported_at', 'verification_status']);

        $summary = $compliance->summary($from, $to);
        $byLevel = collect(Level::compliable())->mapWithKeys(fn (Level $level) => [$level->value => [
            'label' => $level->label(),
            'patuh' => $summary['rows']->where('level', $level)->where('status', Compliance::Patuh)->count(),
            'total' => $summary['rows']->where('level', $level)->whereNotIn('status', [Compliance::Menunggu])->count(),
        ]]);

        return view('livewire.kadis.dashboard', [
            'directions' => $directions->take(8),
            'eselon3Count' => Position::query()->level(Level::Eselon3)->count(),
            'siapCount' => $kadisRecipients->whereNotNull('confirmed_at')->count(),
            'siapTotal' => $kadisRecipients->count(),
            'reported' => $allRecipients->whereNotNull('reported_at')->count(),
            'recipientsTotal' => $allRecipients->count(),
            'verified' => $allRecipients->where('verification_status', 'approved')->count(),
            'summary' => $summary,
            'byLevel' => $byLevel,
            'pendingInitiatives' => Initiative::query()->where('status', 'pending')->with('user', 'position')->latest()->get(),
        ])->title('Dashboard');
    }
}
