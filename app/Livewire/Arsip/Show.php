<?php

namespace App\Livewire\Arsip;

use App\Models\Letter;
use App\Models\LetterDisposition;
use App\Services\ArsipService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::app')]
class Show extends Component
{
    public Letter $letter;

    public string $parafNote = '';

    /** @var array<int, bool> */
    public array $dispositionTargets = [];

    /** @var array<int, bool> indeks harapan (urutan Letter::EXPECTATIONS) => dipilih */
    public array $expectations = [];

    /** @var array<int, string> indeks harapan => isian unit/jabatan */
    public array $expectationCustom = [];

    public string $dispositionNote = '';

    public string $reply = '';

    /** @var array<int, bool> */
    public array $forwardTargets = [];

    public string $forwardNote = '';

    public function mount(Letter $letter, ArsipService $service): void
    {
        abort_unless(ArsipService::canView(auth()->user(), $letter), 403);
        $this->letter = $letter;
        $service->markRead(auth()->user(), $letter);
        $this->reply = (string) $this->myDisposition()?->reply;
    }

    private function myDisposition(): ?LetterDisposition
    {
        return ArsipService::inbox(auth()->user())->where('letter_id', $this->letter->id)->latest('id')->first();
    }

    public function paraf(ArsipService $service): void
    {
        $this->validate(['parafNote' => 'nullable|string|max:1000']);
        $service->paraf(auth()->user(), $this->letter, $this->parafNote ?: null);
        $this->letter->refresh();
        $this->dispatch('toast', message: 'Surat diparaf dan diteruskan ke Kepala Dinas.');
    }

    public function dispose(ArsipService $service): void
    {
        $this->validate(['dispositionNote' => 'nullable|string|max:2000'], attributes: ['dispositionNote' => 'catatan']);

        $selected = collect(Letter::EXPECTATIONS)->flatten(1)->values()
            ->filter(fn ($item, $i) => $this->expectations[$i] ?? false)
            ->map(fn ($item, $i) => isset($item['prefix']) && trim($this->expectationCustom[$i] ?? '') !== ''
                ? $item['prefix'].trim($this->expectationCustom[$i])
                : $item['text'])
            ->values()->all();

        $service->dispose(auth()->user(), $this->letter, collect($this->dispositionTargets)->filter()->keys()->all(), $selected, $this->dispositionNote ?: null);
        $this->reset('dispositionTargets', 'expectations', 'expectationCustom', 'dispositionNote');
        $this->letter->refresh();
        $this->dispatch('toast', message: 'Disposisi terkirim.');
    }

    public function resetDisposition(ArsipService $service): void
    {
        $service->resetDisposition(auth()->user(), $this->letter);
        $this->letter->refresh();
        $this->dispatch('toast', message: 'Disposisi dibatalkan, silakan disposisi ulang.');
    }

    public function sendReply(ArsipService $service): void
    {
        $this->validate(['reply' => 'required|string|max:2000'], attributes: ['reply' => 'balasan']);
        $service->reply(auth()->user(), $this->myDisposition(), $this->reply);
        $this->dispatch('toast', message: 'Balasan terkirim ke pemberi disposisi.');
    }

    public function forward(ArsipService $service): void
    {
        $this->validate(['forwardNote' => 'nullable|string|max:2000']);
        $service->forward(auth()->user(), $this->myDisposition(), collect($this->forwardTargets)->filter()->keys()->all(), $this->forwardNote ?: null);
        $this->reset('forwardTargets', 'forwardNote');
        $this->dispatch('toast', message: 'Disposisi diteruskan ke bawahan.');
    }

    public function complete(bool $done, ArsipService $service): void
    {
        $service->complete(auth()->user(), $this->myDisposition(), $done);
        $this->dispatch('toast', message: $done ? 'Disposisi ditandai selesai.' : 'Disposisi dibuka kembali.');
    }

    public function delete(ArsipService $service)
    {
        $service->delete(auth()->user(), $this->letter);
        session()->flash('toast', 'Surat dihapus.');

        return $this->redirectRoute('arsip.index', navigate: true);
    }

    public function render()
    {
        $user = auth()->user();
        $this->letter->load(['creator', 'operatorPosition', 'verifierPosition.currentAssignment.user', 'parafUser', 'disposer', 'kadisPosition', 'dispositions.toPosition', 'dispositions.toUser', 'dispositions.fromUser', 'dispositions.fromPosition', 'dispositions.whatsappMessages', 'whatsappMessages']);
        $mine = $this->myDisposition();
        $position = $user->position();

        return view('livewire.arsip.show', [
            'mine' => $mine,
            'isVerifier' => ArsipService::isVerifier($user, $this->letter),
            'isKadis' => ArsipService::isKadis($user),
            'canEdit' => ArsipService::canEdit($user, $this->letter),
            'kadisTargets' => ArsipService::isKadis($user) ? $position->subordinates()->with('currentAssignment.user')->get() : collect(),
            'forwardOptions' => $mine ? $position->subordinates()->with('currentAssignment.user')->get() : collect(),
            'alreadyForwarded' => $mine ? $mine->children()->pluck('to_position_id')->all() : [],
            'tree' => $this->letter->dispositions->groupBy(fn ($d) => $d->parent_id ?? 0),
            'canReset' => ArsipService::isKadis($user) && $this->letter->disposed_at && ! $this->letter->dispositions->whereNotNull('parent_id')->count(),
        ])->title('Surat '.$this->letter->agenda_number);
    }
}
