<?php

namespace App\Livewire\Shared;

use App\Enums\Level;
use App\Models\Initiative;
use App\Models\Position;
use App\Services\ComplianceService;
use App\Services\DirectionService;
use App\Services\EmployeeOverview;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::app')]
class Members extends Component
{
    public ?int $fieldPositionId = null;

    public string $destination = '';

    public ?int $reprimandPositionId = null;

    public string $reprimandMessage = '';

    /** @var array<int, string> */
    public array $reviewNotes = [];

    private function target(int $positionId): Position
    {
        $position = Position::query()->findOrFail($positionId);
        abort_unless(auth()->user()->position()->isAncestorOf($position), 403);

        return $position;
    }

    public function openField(int $positionId): void
    {
        $this->target($positionId);
        $this->fieldPositionId = $positionId;
        $this->destination = '';
    }

    public function saveField(DirectionService $service): void
    {
        $this->validate(['destination' => 'nullable|string|max:200']);
        $service->setFieldDuty(auth()->user(), $this->target($this->fieldPositionId), true, $this->destination ?: null);
        $this->reset('fieldPositionId', 'destination');
        $this->dispatch('toast', message: 'Status dinas luar hari ini ditetapkan.');
    }

    public function clearField(int $positionId, DirectionService $service): void
    {
        $service->setFieldDuty(auth()->user(), $this->target($positionId), false);
        $this->dispatch('toast', message: 'Status dinas luar dibatalkan.');
    }

    public function openReprimand(int $positionId): void
    {
        $position = $this->target($positionId);
        $this->reprimandPositionId = $positionId;
        $this->reprimandMessage = 'Anda belum mengonfirmasi/menindaklanjuti instruksi sebelum batas pukul '.ComplianceService::deadline($position->level).'. Mohon segera ditindaklanjuti.';
    }

    public function sendReprimand(DirectionService $service): void
    {
        $this->validate(['reprimandMessage' => 'required|string|max:1000']);
        $service->reprimand(auth()->user(), $this->target($this->reprimandPositionId), $this->reprimandMessage);
        $this->reset('reprimandPositionId', 'reprimandMessage');
        $this->dispatch('toast', message: 'Teguran terkirim.');
    }

    public function review(int $initiativeId, bool $approve, DirectionService $service): void
    {
        $service->reviewInitiative(auth()->user(), Initiative::query()->findOrFail($initiativeId), $approve, $this->reviewNotes[$initiativeId] ?? null);
        $this->dispatch('toast', message: $approve ? 'Inisiatif disetujui.' : 'Inisiatif ditolak.');
    }

    public function render(EmployeeOverview $overview)
    {
        $position = auth()->user()->position();
        $members = $position->level === Level::Eselon3 ? $position->descendants() : $position->children()->with('currentAssignment.user')->get();
        $rows = $overview->rows($members->load('parent'));

        return view('livewire.shared.members', [
            'rows' => $rows,
            'isEselon3' => $position->level === Level::Eselon3,
            'pending' => Initiative::query()->where('status', 'pending')->whereIn('position_id', $position->children->pluck('id'))->with('user', 'position')->latest()->get(),
        ])->title('Anggota');
    }
}
