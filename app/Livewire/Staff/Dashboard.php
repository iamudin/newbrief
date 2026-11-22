<?php

namespace App\Livewire\Staff;

use App\Enums\TaskStatus;
use App\Livewire\Concerns\HandlesCheckinAndInitiative;
use App\Models\Task;
use App\Services\ComplianceService;
use App\Services\DirectionService;
use App\Services\WorkCalendar;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
class Dashboard extends Component
{
    use HandlesCheckinAndInitiative, WithPagination;

    #[Url]
    public string $period = 'week';

    public function updatedPeriod(): void
    {
        $this->resetPage();
    }

    public function render(ComplianceService $compliance)
    {
        $user = auth()->user();
        $position = $user->position();
        [$from, $to] = WorkCalendar::range($this->period);
        $inbox = DirectionService::inbox($user)->whereHas('direction', fn ($q) => $q->whereBetween('date', [$from->toDateString(), $to->toDateString().' 23:59:59']));
        $received = (clone $inbox)->get(['id', 'reported_at', 'verification_status']);
        $tasks = Task::query()->whereIn('direction_recipient_id', $received->pluck('id'))->get(['status']);
        $latest = DirectionService::inbox($user)->whereHas('direction', fn ($q) => $q->whereDate('date', today()))->with('direction.fromPosition', 'direction.parent')->latest('id')->first();

        return view('livewire.staff.dashboard', [
            'position' => $position,
            'checkin' => $this->todayCheckin(),
            'todayCompliance' => $compliance->today($position),
            'deadline' => ComplianceService::deadline($position->level),
            'latest' => $latest,
            'stats' => [
                'received' => $received->count(),
                'done' => $tasks->where('status', TaskStatus::Selesai)->count(),
                'pending' => $tasks->where('status', '!=', TaskStatus::Selesai)->count(),
                'total' => $tasks->count(),
                'reported' => $received->whereNotNull('reported_at')->count(),
                'approved' => $received->where('verification_status', 'approved')->count(),
            ],
            'summary' => $compliance->summary($from, $to, collect([$position->id])),
            'history' => $inbox->with('direction.fromUser', 'tasks')->latest('id')->paginate(8),
        ])->title('Dashboard');
    }
}
