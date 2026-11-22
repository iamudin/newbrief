<?php

namespace App\Livewire\Shared;

use App\Enums\TaskStatus;
use App\Models\Direction;
use App\Models\DirectionRecipient;
use App\Models\Task;
use App\Services\DirectionService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts::app')]
class RecipientDetail extends Component
{
    use WithFileUploads;

    public DirectionRecipient $recipient;

    /** @var array<int, string> */
    public array $taskNotes = [];

    /** @var array<int, mixed> */
    public array $taskFiles = [];

    public string $reportNote = '';

    public $reportFile;

    public string $message = '';

    public string $verificationNote = '';

    public function mount(DirectionRecipient $recipient, DirectionService $service): void
    {
        abort_unless(DirectionService::canView(auth()->user(), $recipient), 403);
        $service->markRead(auth()->user(), $recipient);

        $this->recipient = $recipient;
        $this->taskNotes = $recipient->tasks->pluck('note', 'id')->map(fn ($n) => (string) $n)->all();
        $this->reportNote = (string) $recipient->report_note;
    }

    public function confirm(?array $coords, DirectionService $service): void
    {
        $service->confirm(auth()->user(), $this->recipient, $coords);
        $this->dispatch('toast', message: 'Konfirmasi SIAP terkirim ke atasan.');
    }

    public function updateTask(int $taskId, string $status, ?array $coords, DirectionService $service): void
    {
        $this->validate([
            "taskNotes.$taskId" => 'nullable|string|max:1000',
        ], attributes: ["taskNotes.$taskId" => 'catatan']);

        abort_unless(in_array($status, array_column(TaskStatus::cases(), 'value'), true), 422);

        $task = Task::query()->where('direction_recipient_id', $this->recipient->id)->findOrFail($taskId);
        $service->updateTask(auth()->user(), $task, $status, $this->taskNotes[$taskId] ?? null, null, $coords);
        $this->recipient->refresh();
        $this->dispatch('toast', message: 'Status tugas diperbarui: '.TaskStatus::from($status)->label());
    }

    /** Lampiran item tugas langsung tersimpan begitu selesai diunggah. */
    public function updatedTaskFiles($file, $taskId): void
    {
        $taskId = (int) $taskId;
        $this->validate(
            ["taskFiles.$taskId" => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            attributes: ["taskFiles.$taskId" => 'file'],
        );

        $task = Task::query()->where('direction_recipient_id', $this->recipient->id)->findOrFail($taskId);
        app(DirectionService::class)->attachTaskFile(auth()->user(), $task, $this->taskFiles[$taskId]);
        unset($this->taskFiles[$taskId]);
        $this->recipient->refresh();
        $this->dispatch('toast', message: 'Lampiran tersimpan: '.$task->fresh()->file_name);
    }

    public function sendReport(?array $coords, DirectionService $service): void
    {
        $this->validate([
            'reportNote' => 'nullable|string|max:2000',
            'reportFile' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        $service->report(auth()->user(), $this->recipient->load('tasks'), $this->reportNote ?: null, $this->reportFile, $coords);
        $this->reset('reportFile');
        $this->recipient->refresh();
        $this->dispatch('toast', message: 'Laporan terkirim ke atasan.');
    }

    public function verify(string $status, DirectionService $service): void
    {
        $this->validate(['verificationNote' => $status === 'revision' ? 'required|string|max:1000' : 'nullable|string|max:1000'], attributes: ['verificationNote' => 'catatan']);
        $service->verify(auth()->user(), $this->recipient, $status, $this->verificationNote ?: null);
        $this->reset('verificationNote');
        $this->recipient->refresh();
        $this->dispatch('toast', message: $status === 'approved' ? 'Laporan diterima.' : 'Laporan dikembalikan untuk revisi.');
    }

    public function sendMessage(DirectionService $service): void
    {
        $this->validate(['message' => 'required|string|max:1000'], attributes: ['message' => 'pesan']);
        $service->message(auth()->user(), $this->recipient, $this->message);
        $this->reset('message');
    }

    public function render()
    {
        $user = auth()->user();
        $this->recipient->load(['direction.fromUser', 'direction.fromPosition', 'direction.parent.fromUser', 'toPosition', 'toUser', 'tasks', 'messages.user', 'verifier']);

        $forwarded = Direction::query()
            ->where('parent_id', $this->recipient->direction_id)
            ->where('from_position_id', $this->recipient->to_position_id)
            ->with(['recipients.toPosition', 'recipients.toUser', 'recipients.tasks'])
            ->get();

        return view('livewire.shared.recipient-detail', [
            'direction' => $this->recipient->direction,
            'isRecipient' => DirectionService::isRecipient($user, $this->recipient),
            'isSender' => DirectionService::isSender($user, $this->recipient),
            'forwarded' => $forwarded,
            'statuses' => TaskStatus::cases(),
        ])->title($this->recipient->direction->typeLabel());
    }
}
