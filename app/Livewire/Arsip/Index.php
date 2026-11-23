<?php

namespace App\Livewire\Arsip;

use App\Enums\Level;
use App\Models\Letter;
use App\Services\ArsipService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'todo';

    #[Url]
    public string $search = '';

    public function updated($name): void
    {
        if (in_array($name, ['tab', 'search'], true)) {
            $this->resetPage();
        }
    }

    /**
     * ID surat beserta keterangan tindakan untuk pengguna.
     *
     * @return Collection<int, string> letter_id => keterangan
     */
    private function letterIds(bool $todo): Collection
    {
        $user = auth()->user();
        $position = $user->position();
        $ids = collect();

        if (ArsipService::isOperator($user)) {
            return Letter::query()->where('operator_position_id', $position->id)
                ->when($todo, fn ($q) => $q->whereNull('disposed_at'), fn ($q) => $q->whereNotNull('disposed_at'))
                ->pluck('id')->mapWithKeys(fn ($id) => [$id => $todo ? 'Dalam proses' : 'Selesai diproses']);
        }

        Letter::query()->where('verifier_position_id', $position->id)
            ->when($todo, fn ($q) => $q->whereNull('paraf_at'), fn ($q) => $q->whereNotNull('paraf_at'))
            ->pluck('id')->each(fn ($id) => $ids[$id] = $todo ? 'Perlu paraf' : 'Sudah diparaf');

        if ($position->level === Level::Kadis) {
            Letter::query()->whereNotNull('paraf_at')
                ->when($todo, fn ($q) => $q->whereNull('disposed_at'), fn ($q) => $q->whereNotNull('disposed_at'))
                ->pluck('id')->each(fn ($id) => $ids[$id] = $todo ? 'Perlu disposisi' : 'Sudah didisposisi');
        }

        ArsipService::inbox($user)
            ->when($todo, fn ($q) => $q->whereNull('completed_at'), fn ($q) => $q->whereNotNull('completed_at'))
            ->get(['letter_id', 'read_at'])
            ->each(fn ($d) => $ids[$d->letter_id] ??= $todo ? ($d->read_at ? 'Tindak lanjuti disposisi' : 'Disposisi baru') : 'Disposisi selesai');

        return $ids;
    }

    public function render()
    {
        $user = auth()->user();
        $context = $this->letterIds($this->tab !== 'done');

        $letters = Letter::query()
            ->whereIn('id', $context->keys())
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('sender', 'like', "%{$this->search}%")
                ->orWhere('letter_number', 'like', "%{$this->search}%")
                ->orWhere('agenda_number', 'like', "%{$this->search}%")
                ->orWhere('subject', 'like', "%{$this->search}%")))
            ->with(['dispositions.toPosition', 'dispositions.toUser', 'dispositions.whatsappMessages', 'whatsappMessages', 'verifierPosition'])
            ->latest('received_date')->latest('id')
            ->paginate(12);

        $myDispositions = ArsipService::inbox($user)->whereIn('letter_id', $letters->pluck('id'))->get()->keyBy('letter_id');

        return view('livewire.arsip.index', [
            'letters' => $letters,
            'context' => $context,
            'myDispositions' => $myDispositions,
            'isOperator' => ArsipService::isOperator($user),
            'todoCount' => $this->letterIds(true)->count(),
        ])->title('E-Arsip');
    }
}
