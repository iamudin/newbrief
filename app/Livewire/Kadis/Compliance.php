<?php

namespace App\Livewire\Kadis;

use App\Enums\Level;
use App\Models\Position;
use App\Models\Reprimand;
use App\Models\User;
use App\Services\ComplianceService;
use App\Services\DirectionService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::app')]
class Compliance extends Component
{
    #[Url]
    public string $tab = 'eselon3';

    #[Url]
    public string $date = '';

    public ?int $reprimandPositionId = null;

    public string $reprimandMessage = '';

    public function mount(): void
    {
        $this->date = $this->date ?: today()->toDateString();
    }

    public function openReprimand(int $positionId): void
    {
        $position = Position::query()->findOrFail($positionId);
        $deadline = ComplianceService::deadline($position->level);
        $this->reprimandPositionId = $positionId;
        $this->reprimandMessage = "Anda belum mengonfirmasi/meneruskan instruksi sebelum batas pukul {$deadline}. Mohon ditindaklanjuti dan tepat waktu ke depannya.";
    }

    public function sendReprimand(DirectionService $service): void
    {
        $this->validate(['reprimandMessage' => 'required|string|max:1000'], attributes: ['reprimandMessage' => 'pesan teguran']);
        $service->reprimand(auth()->user(), Position::query()->findOrFail($this->reprimandPositionId), $this->reprimandMessage);
        $this->reset('reprimandPositionId', 'reprimandMessage');
        $this->dispatch('toast', message: 'Teguran terkirim.');
    }

    public function render(ComplianceService $compliance)
    {
        $level = Level::tryFrom($this->tab) ?? Level::Eselon3;
        $date = min($this->date ?: today()->toDateString(), today()->toDateString());
        $positions = Position::query()->level($level)->with('currentAssignment.user', 'parent')->orderBy('sort_order')->get();
        $results = $compliance->forDate($date, $positions->pluck('id'));
        $reprimanded = Reprimand::query()->whereDate('date', $date)->pluck('to_position_id')->flip();

        $rows = $positions->map(fn (Position $p) => [
            'position' => $p,
            'user' => $results->get($p->id) ? User::find($results->get($p->id)['user_id']) : $p->holder(),
            'result' => $results->get($p->id),
            'reprimanded' => $reprimanded->has($p->id),
        ])->sortBy(fn ($r) => $r['result'] ? ($r['result']['status']->isViolation() ? 0 : 1) : 2);

        $counts = collect(Level::compliable())->mapWithKeys(fn (Level $l) => [$l->value => $compliance->forDate($date, Position::query()->level($l)->pluck('id'))->filter(fn ($r) => $r['status']->isViolation())->count()]);

        return view('livewire.kadis.compliance', [
            'rows' => $rows,
            'level' => $level,
            'deadline' => ComplianceService::deadline($level),
            'counts' => $counts,
            'isToday' => $date === today()->toDateString(),
        ])->title('Kepatuhan');
    }
}
