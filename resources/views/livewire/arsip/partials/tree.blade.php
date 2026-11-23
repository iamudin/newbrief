<ul class="flex flex-col gap-2 {{ $depth ? 'mt-2 ml-4 border-l-2 border-dashed border-line pl-3' : '' }}">
    @foreach ($tree->get($parentId, collect()) as $d)
        <li wire:key="d-{{ $d->id }}">
            <div @class(['rounded-xl border p-2.5', 'border-gold bg-gold-soft/30' => $mine?->id === $d->id, 'border-line' => $mine?->id !== $d->id])>
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="text-sm font-semibold">{{ $d->toPosition->name }}</div>
                        <div class="text-[11px] text-muted">{{ $d->toUser?->name ?? 'Jabatan kosong' }} · dari {{ $d->fromPosition->name }} · {{ $d->created_at->format('d/m H.i') }}</div>
                    </div>
                    <span class="chip {{ $d->stateClass() }}">{{ $d->stateLabel() }}</span>
                </div>
                @if ($d->parent_id && $d->note)<p class="mt-1.5 rounded-lg bg-surface px-2.5 py-1.5 text-xs"><b>Catatan:</b> {{ $d->note }}</p>@endif
                @if ($d->reply)<p class="mt-1.5 rounded-lg bg-sky-50 px-2.5 py-1.5 text-xs"><b>Balasan</b> ({{ $d->replied_at->format('d/m H.i') }}): {{ $d->reply }}</p>@endif
                <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[10px] text-muted">
                    <span>Dibaca {{ $d->read_at?->format('d/m H.i') ?? '—' }} · Selesai {{ $d->completed_at?->format('d/m H.i') ?? '—' }}</span>
                    <x-wa-status :message="$d->whatsappFor()" />
                </div>
            </div>
            @include('livewire.arsip.partials.tree', ['parentId' => $d->id, 'depth' => $depth + 1])
        </li>
    @endforeach
</ul>
