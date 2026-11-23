<?php

namespace App\Livewire\Admin;

use App\Enums\Level;
use App\Models\DirectionRecipient;
use App\Models\Position;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Struktur atasan–bawahan berbasis jabatan, diatur dengan nested drag & drop. */
#[Layout('layouts::admin')]
class Structure extends Component
{
    public int $version = 0;

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $level = 'staff';

    public string $category = '';

    public string $unit = '';

    public ?int $parentId = null;

    public function create(?int $parentId = null): void
    {
        $parent = $parentId ? Position::query()->find($parentId) : null;
        $this->reset('editingId', 'name', 'category', 'unit');
        $this->parentId = $parentId;
        $this->level = $parent?->level->child()?->value ?? Level::Kadis->value;
        $this->unit = (string) $parent?->unit;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $p = Position::query()->findOrFail($id);
        $this->fill(['editingId' => $id, 'name' => $p->name, 'level' => $p->level->value, 'category' => (string) $p->category, 'unit' => (string) $p->unit, 'parentId' => $p->parent_id]);
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:150',
            'level' => ['required', Rule::enum(Level::class)],
            'category' => 'nullable|string|max:30',
            'unit' => 'nullable|string|max:150',
            'parentId' => 'nullable|exists:positions,id',
        ]);

        $level = Level::from($this->level);
        $parent = $this->parentId ? Position::query()->find($this->parentId) : null;

        if ($error = $this->hierarchyError($level, $parent?->level)) {
            $this->addError('level', $error);

            return;
        }

        if ($this->editingId && $this->level !== Position::query()->find($this->editingId)->level->value && Position::query()->where('parent_id', $this->editingId)->exists()) {
            $this->addError('level', 'Jenjang tidak dapat diubah selama jabatan masih memiliki bawahan.');

            return;
        }

        Position::query()->updateOrCreate(['id' => $this->editingId], [
            'name' => $this->name,
            'level' => $level,
            'category' => $this->category ?: null,
            'unit' => $this->unit ?: null,
            'parent_id' => $parent?->id,
            'sort_order' => $this->editingId ? Position::query()->find($this->editingId)->sort_order : (Position::query()->where('parent_id', $parent?->id)->max('sort_order') + 1),
        ]);

        $this->showForm = false;
        $this->version++;
        $this->dispatch('toast', message: 'Jabatan disimpan.');
    }

    public function delete(int $id): void
    {
        $p = Position::query()->withCount('children', 'assignments')->findOrFail($id);

        if ($p->children_count > 0) {
            $this->dispatch('toast', message: 'Pindahkan atau hapus bawahan terlebih dahulu.', tone: 'error');

            return;
        }

        if ($p->currentAssignment) {
            $this->dispatch('toast', message: 'Lepaskan pemegang jabatan terlebih dahulu (menu Data Pegawai).', tone: 'error');

            return;
        }

        if ($p->assignments_count > 0 || DirectionRecipient::query()->where('to_position_id', $id)->exists()) {
            $this->dispatch('toast', message: 'Jabatan memiliki riwayat tugas; tidak dapat dihapus agar riwayat tetap utuh.', tone: 'error');

            return;
        }

        $p->delete();
        $this->version++;
        $this->dispatch('toast', message: 'Jabatan dihapus.');
    }

    /**
     * Simpan hasil drag & drop.
     *
     * @param  array<int, array{id:int, children?: array}>  $tree
     */
    public function saveTree(array $tree): void
    {
        $positions = Position::query()->get()->keyBy('id');
        $updates = [];
        $error = null;

        $walk = function (array $nodes, ?int $parentId) use (&$walk, &$updates, &$error, $positions) {
            foreach (array_values($nodes) as $order => $node) {
                $position = $positions->get((int) ($node['id'] ?? 0));

                if (! $position) {
                    continue;
                }

                $parentLevel = $parentId ? $positions[$parentId]->level : null;
                $error ??= ($e = $this->hierarchyError($position->level, $parentLevel)) ? $position->name.': '.$e : null;
                $updates[$position->id] = ['parent_id' => $parentId, 'sort_order' => $order];
                $walk($node['children'] ?? [], $position->id);
            }
        };

        $walk($tree, null);
        $this->version++;

        if ($error) {
            $this->dispatch('toast', message: $error, tone: 'error');

            return;
        }

        DB::transaction(function () use ($updates) {
            foreach ($updates as $id => $data) {
                Position::query()->whereKey($id)->update($data);
            }
        });

        $this->dispatch('toast', message: 'Struktur diperbarui.');
    }

    private function hierarchyError(Level $level, ?Level $parentLevel): ?string
    {
        if ($parentLevel === null) {
            return $level === Level::Kadis ? null : 'Jabatan selain Kepala Dinas harus memiliki atasan.';
        }

        $allowed = $level->allowedParents();

        return in_array($parentLevel, $allowed, true) ? null : $level->label().' harus berada langsung di bawah '.(collect($allowed)->map->label()->join(' / ') ?: 'tidak ada').'.';
    }

    public function render()
    {
        $all = Position::query()->with('currentAssignment.user')->withCount('duties')->orderBy('sort_order')->orderBy('id')->get();

        return view('livewire.admin.structure', [
            'roots' => $all->whereNull('parent_id'),
            'all' => $all->groupBy('parent_id'),
            'parents' => $all->where('level', '!=', Level::Staff),
            'categories' => Level::categories(Level::from($this->level)),
        ])->title('Struktur Pegawai');
    }
}
