<?php

namespace App\Livewire\Admin;

use App\Models\Duty;
use App\Models\Position;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Tupoksi melekat ke jabatan, sehingga pemegang baru langsung mewarisinya. */
#[Layout('layouts::admin')]
class Duties extends Component
{
    #[Url]
    public ?int $positionId = null;

    #[Locked]
    public ?int $editingId = null;

    public string $title = '';

    public string $description = '';

    public function mount(): void
    {
        $this->positionId ??= Position::query()->orderBy('id')->value('id');
    }

    public function updatedPositionId(): void
    {
        $this->reset('editingId', 'title', 'description');
    }

    public function edit(int $id): void
    {
        $duty = Duty::query()->findOrFail($id);
        $this->fill(['editingId' => $id, 'title' => $duty->title, 'description' => (string) $duty->description]);
    }

    public function save(): void
    {
        $this->validate(['positionId' => 'required|exists:positions,id', 'title' => 'required|string|max:255', 'description' => 'nullable|string|max:1000'], attributes: ['title' => 'uraian tupoksi']);

        Duty::query()->updateOrCreate(['id' => $this->editingId], [
            'position_id' => $this->positionId,
            'title' => $this->title,
            'description' => $this->description ?: null,
            'sort_order' => $this->editingId ? Duty::query()->find($this->editingId)->sort_order : (int) Duty::query()->where('position_id', $this->positionId)->max('sort_order') + 1,
        ]);

        $this->reset('editingId', 'title', 'description');
        $this->dispatch('toast', message: 'Tupoksi disimpan.');
    }

    public function delete(int $id): void
    {
        Duty::query()->whereKey($id)->delete();
    }

    /** @param array<int, int> $ids */
    public function reorder(array $ids): void
    {
        foreach (array_values($ids) as $i => $id) {
            Duty::query()->whereKey($id)->where('position_id', $this->positionId)->update(['sort_order' => $i]);
        }
    }

    public function render()
    {
        $positions = Position::query()->withCount('duties')->with('currentAssignment.user')->get()->sortBy(fn ($p) => [$p->level->rank(), $p->parent_id, $p->sort_order]);

        return view('livewire.admin.duties', [
            'positions' => $positions,
            'position' => $positions->firstWhere('id', $this->positionId),
            'duties' => Duty::query()->where('position_id', $this->positionId)->orderBy('sort_order')->get(),
        ])->title('Tupoksi Jabatan');
    }
}
