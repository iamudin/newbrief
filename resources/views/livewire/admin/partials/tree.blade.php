<ul class="tree-list flex min-h-3 flex-col gap-1.5 {{ $depth ? 'mt-1.5 ml-7 border-l-2 border-dashed border-line pl-3' : '' }}" data-parent="{{ $parentId ?? '' }}">
    @foreach ($nodes as $node)
        @php
            $colors = ['kadis' => 'bg-navy text-gold', 'eselon3' => 'bg-navy-2 text-white', 'eselon4' => 'bg-info-soft text-navy', 'staff' => 'bg-ok-soft text-ok-dark'];
        @endphp
        <li data-id="{{ $node->id }}" class="tree-item">
            <div class="group flex items-center gap-2 rounded-xl border border-line bg-white px-3 py-2 hover:border-navy-3">
                <span class="tree-handle cursor-grab text-muted active:cursor-grabbing" title="Seret untuk memindahkan"><x-icon name="drag" size="16" /></span>
                <span class="chip {{ $colors[$node->level->value] }}">{{ $node->level->label() }}</span>
                <span class="text-sm font-semibold">{{ $node->name }}</span>
                <span class="text-xs {{ $node->currentAssignment ? 'text-muted' : 'font-semibold text-rose-600' }}">· {{ $node->currentAssignment?->user?->name ?? 'KOSONG' }}</span>
                <span class="text-[11px] text-muted">· {{ $node->duties_count }} tupoksi</span>
                <span class="ml-auto flex gap-1 opacity-0 group-hover:opacity-100">
                    @if ($node->level->child())<button type="button" wire:click="create({{ $node->id }})" class="btn-outline btn-sm" title="Tambah bawahan"><x-icon name="plus" size="13" /></button>@endif
                    <button type="button" wire:click="edit({{ $node->id }})" class="btn-outline btn-sm" title="Ubah"><x-icon name="edit" size="13" /></button>
                    <button type="button" wire:click="delete({{ $node->id }})" wire:confirm="Hapus jabatan {{ $node->name }}?" class="btn-outline btn-sm !text-rose-600" title="Hapus"><x-icon name="trash" size="13" /></button>
                </span>
            </div>
            @if ($node->level->child())
                @include('livewire.admin.partials.tree', ['nodes' => $all->get($node->id, collect()), 'parentId' => $node->id, 'depth' => $depth + 1])
            @endif
        </li>
    @endforeach
</ul>
