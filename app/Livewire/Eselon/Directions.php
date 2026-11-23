<?php

namespace App\Livewire\Eselon;

use App\Enums\Level;
use App\Models\DirectionRecipient;
use App\Models\Initiative;
use App\Services\DirectionService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Eselon 3: "Arahan" ke Eselon 4.  Eselon 4: "Bagi & Verifikasi" ke Staff.
 * Instruksi dibuat dengan checklist item: manual, dari tupoksi bawahan, atau inisiatif yang disetujui.
 */
#[Layout('layouts::app')]
class Directions extends Component
{
    use WithFileUploads;

    public ?int $parentId = null;

    public string $content = '';

    public $attachment;

    /** Item umum untuk semua penerima, satu per baris. */
    public string $common = '';

    /** @var array<int, bool> */
    public array $selected = [];

    /** @var array<int, array<int, bool>> */
    public array $duties = [];

    /** @var array<int, array<int, bool>> */
    public array $initiatives = [];

    /** @var array<int, string> */
    public array $manual = [];

    public function mount(): void
    {
        $this->parentId = $this->todayInbox()->first()?->direction_id;
    }

    private function todayInbox(): Collection
    {
        return DirectionService::inbox(auth()->user())
            ->whereHas('direction', fn ($q) => $q->whereDate('date', today()))
            ->with('direction.fromUser', 'direction.fromPosition', 'tasks')
            ->latest('id')
            ->get();
    }

    public function selectAll(): void
    {
        foreach (auth()->user()->position()->subordinates as $child) {
            $this->selected[$child->id] = true;
        }
    }

    public function confirm(int $recipientId, ?array $coords, DirectionService $service): void
    {
        $service->confirm(auth()->user(), DirectionRecipient::query()->findOrFail($recipientId), $coords);
        $this->dispatch('toast', message: 'Konfirmasi SIAP terkirim.');
    }

    public function send(?array $coords, DirectionService $service): void
    {
        $this->validate([
            'content' => 'required|string|max:5000',
            'attachment' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
            'common' => 'nullable|string|max:3000',
        ], attributes: ['content' => 'isi instruksi', 'attachment' => 'lampiran']);

        $commonItems = $this->lines($this->common);
        $recipients = collect($this->selected)->filter()->keys()->map(fn ($positionId) => [
            'position_id' => $positionId,
            'items' => [
                ...collect($commonItems)->map(fn ($t) => ['title' => $t, 'source' => 'manual']),
                ...collect($this->duties[$positionId] ?? [])->filter()->keys()->map(fn ($id) => ['source' => 'duty', 'duty_id' => $id, 'title' => '']),
                ...collect($this->initiatives[$positionId] ?? [])->filter()->keys()->map(fn ($id) => ['source' => 'initiative', 'initiative_id' => $id, 'title' => '']),
                ...collect($this->lines($this->manual[$positionId] ?? ''))->map(fn ($t) => ['title' => $t, 'source' => 'manual']),
            ],
        ])->values()->all();

        $service->send(auth()->user(), 'task', $this->content, $recipients, null, $this->parentId ?: null, $this->attachment, $coords);

        $this->reset('content', 'attachment', 'common', 'selected', 'duties', 'initiatives', 'manual');
        $this->dispatch('toast', message: 'Instruksi terkirim ke '.count($recipients).' bawahan.');
    }

    /** @return array<int, string> */
    private function lines(string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text))->map(fn ($l) => trim($l))->filter()->values()->all();
    }

    public function render()
    {
        $user = auth()->user();
        $position = $user->position();
        $children = $position->subordinates()->with('currentAssignment.user', 'duties')->get();

        $approvedInitiatives = Initiative::query()
            ->whereIn('position_id', $children->pluck('id'))
            ->where('status', 'approved')
            ->latest()
            ->get()
            ->groupBy('position_id');

        $outbox = DirectionRecipient::query()
            ->whereHas('direction', fn ($q) => $q->where('from_position_id', $position->id))
            ->with('direction', 'toPosition', 'toUser', 'tasks', 'whatsappMessages')
            ->latest('id');

        return view('livewire.eselon.directions', [
            'isEselon3' => $position->level === Level::Eselon3,
            'inbox' => $this->todayInbox(),
            'children' => $children,
            'approvedInitiatives' => $approvedInitiatives,
            'toVerify' => (clone $outbox)->whereNotNull('reported_at')->whereNull('verification_status')->get(),
            'sentToday' => (clone $outbox)->whereHas('direction', fn ($q) => $q->whereDate('date', today()))->get(),
        ])->title($position->level === Level::Eselon3 ? 'Arahan' : 'Bagi & Verifikasi');
    }
}
