<div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
        <p class="text-sm text-muted">Seret <x-icon name="drag" size="14" class="inline" /> untuk memindahkan jabatan beserta bawahannya. Aturan: Kepala Dinas → Eselon 3 → Eselon 4 → Staff.</p>
        <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" size="16" />Jabatan baru</button>
    </div>

    <div class="card" wire:key="tree-{{ $version }}" x-data="{
            init() {
                const serialize = (ul) => [...ul.children].filter(li => li.dataset.id).map(li => ({ id: +li.dataset.id, children: li.querySelector(':scope > ul') ? serialize(li.querySelector(':scope > ul')) : [] }));
                this.$el.querySelectorAll('ul.tree-list').forEach(ul => new Sortable(ul, {
                    group: 'positions', handle: '.tree-handle', animation: 150, fallbackOnBody: true, swapThreshold: 0.65, emptyInsertThreshold: 12,
                    ghostClass: 'opacity-40',
                    onEnd: () => $wire.saveTree(serialize(this.$el.querySelector('ul.tree-list'))),
                }));
            }
        }">
        @include('livewire.admin.partials.tree', ['nodes' => $roots, 'parentId' => null, 'depth' => 0])
    </div>

    <x-modal :show="$showForm" :title="$editingId ? 'Ubah jabatan' : 'Jabatan baru'" close="$set('showForm', false)">
        <form wire:submit="save" class="grid grid-cols-2 gap-3">
            <div class="col-span-2"><label class="label">Nama jabatan</label><input wire:model="name" class="field" placeholder="mis. Kasubag Umum & Kepegawaian">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Jenjang</label><select wire:model.live="level" class="field">@foreach (\App\Enums\Level::cases() as $l)<option value="{{ $l->value }}">{{ $l->label() }}</option>@endforeach</select>@error('level')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Kategori</label><select wire:model="category" class="field"><option value="">—</option>@foreach ($categories as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="col-span-2"><label class="label">Atasan</label><select wire:model="parentId" class="field"><option value="">— (Kepala Dinas tanpa atasan)</option>@foreach ($parents as $p)<option value="{{ $p->id }}">[{{ $p->level->label() }}] {{ $p->name }}</option>@endforeach</select></div>
            <div class="col-span-2"><label class="label">Unit / bidang</label><input wire:model="unit" class="field"></div>
            <button class="btn-primary col-span-2">Simpan</button>
        </form>
    </x-modal>
</div>
