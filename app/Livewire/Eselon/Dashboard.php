<?php

namespace App\Livewire\Eselon;

use App\Enums\Level;
use App\Enums\TaskStatus;
use App\Livewire\Concerns\HandlesCheckinAndInitiative;
use App\Models\DirectionRecipient;
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
        $range = [$from->toDateString(), $to->toDateString().' 23:59:59'];
        $inPeriod = fn ($q) => $q->whereBetween('date', $range);

        $inbox = DirectionService::inbox($user)->whereHas('direction', $inPeriod);
        $received = (clone $inbox)->get(['id', 'confirmed_at', 'reported_at']);

        $myTasks = Task::query()->whereIn('direction_recipient_id', $received->pluck('id'))->get(['status']);
        $teamRecipients = DirectionRecipient::query()
            ->whereHas('direction', fn ($q) => $q->where('from_position_id', $position->id)->whereBetween('date', $range))
            ->get(['id', 'reported_at', 'verification_status']);
        $teamTasks = Task::query()->whereIn('direction_recipient_id', $teamRecipients->pluck('id'))->get(['status']);

        $summary = $compliance->summary($from, $to, collect([$position->id]));

        return view('livewire.eselon.dashboard', [
            'isEselon3' => $position->level === Level::Eselon3,
            'position' => $position,
            'checkin' => $this->todayCheckin(),
            'todayCompliance' => $compliance->today($position),
            'deadline' => ComplianceService::deadline($position->level),
            'stats' => [
                'received' => $received->count(),
                'confirmed' => $received->whereNotNull('confirmed_at')->count(),
                'my_done' => $myTasks->where('status', TaskStatus::Selesai)->count(),
                'my_total' => $myTasks->count(),
                'team_done' => $teamTasks->where('status', TaskStatus::Selesai)->count(),
                'team_total' => $teamTasks->count(),
                'team_pending' => $teamTasks->where('status', '!=', TaskStatus::Selesai)->count(),
                'team_verified' => $teamRecipients->where('verification_status', 'approved')->count(),
                'team_reported' => $teamRecipients->whereNotNull('reported_at')->count(),
            ],
            'summary' => $summary,
            'history' => $inbox->with('direction.fromUser', 'direction.fromPosition', 'tasks')->latest('id')->paginate(8),
            'initiatives' => $this->myInitiatives(),
        ])->title('Dashboard');
    }
}
