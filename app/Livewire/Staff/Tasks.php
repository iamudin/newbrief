<?php

namespace App\Livewire\Staff;

use App\Enums\TaskStatus;
use App\Livewire\Concerns\HandlesCheckinAndInitiative;
use App\Models\Task;
use App\Services\ComplianceService;
use App\Services\DirectionService;
use App\Services\LocationService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::app')]
class Tasks extends Component
{
    use HandlesCheckinAndInitiative, WithFileUploads;

    /** @var array<int, mixed> */
    public array $taskFiles = [];

    /** Lampiran bukti langsung tersimpan begitu selesai diunggah. */
    public function updatedTaskFiles($file, $taskId): void
    {
        $taskId = (int) $taskId;
        $this->validate(
            ["taskFiles.$taskId" => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            attributes: ["taskFiles.$taskId" => 'file'],
        );

        $task = Task::query()->with('recipient')->findOrFail($taskId);
        app(DirectionService::class)->attachTaskFile(auth()->user(), $task, $this->taskFiles[$taskId]);
        unset($this->taskFiles[$taskId]);
        $this->dispatch('toast', message: 'Lampiran tersimpan: '.$task->fresh()->file_name);
    }

    public function updateTask(int $taskId, string $status, ?array $coords, DirectionService $service): void
    {
        abort_unless(in_array($status, array_column(TaskStatus::cases(), 'value'), true), 422);
        $task = Task::query()->with('recipient.direction')->findOrFail($taskId);
        $service->updateTask(auth()->user(), $task, $status, $task->note, null, $coords);
        $this->dispatch('toast', message: 'Status: '.TaskStatus::from($status)->label());
    }

    public function render()
    {
        $user = auth()->user();
        $recipients = DirectionService::inbox($user)
            ->whereHas('direction', fn ($q) => $q->whereDate('date', today()))
            ->with('direction.fromUser', 'direction.fromPosition', 'tasks')
            ->latest('id')
            ->get();

        $all = $recipients->flatMap->tasks;

        return view('livewire.staff.tasks', [
            'recipients' => $recipients,
            'done' => $all->where('status', TaskStatus::Selesai)->count(),
            'total' => $all->count(),
            'checkin' => $this->todayCheckin(),
            'deadline' => ComplianceService::deadline($user->level()),
            'onField' => app(LocationService::class)->isOnFieldDuty($user),
        ])->title('Tugas Hari Ini');
    }
}
