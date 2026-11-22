<div class="grid grid-cols-3 gap-4">
    <section class="card flex max-h-[75dvh] flex-col gap-1 overflow-y-auto !p-2">
        @foreach ($positions as $p)
            <button type="button" wire:click="$set('positionId', {{ $p->id }})" @class(['flex cursor-pointer items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm', 'bg-navy text-white' => $positionId === $p->id, 'hover:bg-surface' => $positionId !== $p->id])>
                <span><span class="block font-semibold">{{ $p->name }}</span><span @class(['block text-[11px]', 'text-[#C9D3E3]' => $positionId === $p->id, 'text-muted' => $positionId !== $p->id])>{{ $p->level->label() }} · {{ $p->holder()?->name ?? 'kosong' }}</span></span>
                <span class="chip {{ $p->duties_count ? 'bg-ok-soft text-ok-dark' : 'bg-rose-100 text-rose-700' }}">{{ $p->duties_count }}</span>
            </button>
        @endforeach
    </section>

    <section class="card col-span-2 flex flex-col gap-3 self-start">
        @if ($position)
            <div><h2 class="text-base font-bold text-navy">{{ $position->name }}</h2><p class="text-xs text-muted">Tupoksi ini menjadi pilihan checklist saat atasan memberi instruksi.</p></div>
            <ul class="flex flex-col gap-1.5" wire:key="duties-{{ $positionId }}-{{ $duties->count() }}" x-data x-init="new Sortable($el, { handle: '.handle', animation: 150, onEnd: () => $wire.reorder([...$el.children].map(li => +li.dataset.id)) })">
                @forelse ($duties as $i => $duty)
                    <li data-id="{{ $duty->id }}" class="flex items-start gap-2 rounded-xl border border-line p-2.5">
                        <span class="handle mt-0.5 cursor-grab text-muted"><x-icon name="drag" size="16" /></span>
                        <span class="flex-1 text-sm"><b>{{ $duty->title }}</b>@if ($duty->description)<span class="block text-xs text-muted">{{ $duty->description }}</span>@endif</span>
                        <button type="button" wire:click="edit({{ $duty->id }})" class="btn-outline btn-sm"><x-icon name="edit" size="13" /></button>
                        <button type="button" wire:click="delete({{ $duty->id }})" wire:confirm="Hapus tupoksi ini?" class="btn-outline btn-sm !text-rose-600"><x-icon name="trash" size="13" /></button>
                    </li>
                @empty
                    <x-empty>Belum ada tupoksi.</x-empty>
                @endforelse
            </ul>
            <form wire:submit="save" class="flex flex-col gap-2 rounded-xl bg-surface p-3">
                <span class="label !mb-0">{{ $editingId ? 'Ubah tupoksi' : 'Tambah tupoksi' }}</span>
                <input wire:model="title" class="field" placeholder="Uraian tugas pokok">
                @error('title')<p class="error">{{ $message }}</p>@enderror
                <textarea wire:model="description" rows="2" class="field" placeholder="Keterangan (opsional)"></textarea>
                <div class="flex gap-2"><button class="btn-primary">Simpan</button>@if ($editingId)<button type="button" wire:click="$set('editingId', null)" class="btn-outline">Batal</button>@endif</div>
            </form>
        @endif
    </section>
</div>
