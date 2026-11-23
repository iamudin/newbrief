<div>
    <x-page-header title="Arahan Kepala Dinas" subtitle="Arahan diterima langsung oleh Eselon 3" />

    <main class="mx-auto grid w-full max-w-6xl gap-3 px-4 py-4 lg:grid-cols-5 lg:px-8">
        <form wire:submit="send" class="flex flex-col gap-3 lg:col-span-3">
            <section class="card flex flex-col gap-2">
                <h2 class="card-title">Arahan umum <span class="font-normal text-muted">· untuk semua Eselon 3</span></h2>
                <input type="text" wire:model="generalTitle" class="field" placeholder="Judul (opsional)">
                <textarea wire:model="generalContent" rows="4" class="field" placeholder="Tulis arahan umum hari ini…"></textarea>
                @error('generalContent')<p class="error">{{ $message }}</p>@enderror
                <label class="label !mb-0">Lampiran (opsional)</label>
                <input type="file" wire:model="generalFile" class="text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-surface file:px-3 file:py-1.5 file:text-xs file:font-semibold">
                @error('generalFile')<p class="error">{{ $message }}</p>@enderror
            </section>

            <section class="card flex flex-col gap-3">
                <h2 class="card-title">Arahan prioritas <span class="font-normal text-muted">· khusus per Eselon 3 (opsional)</span></h2>
                @foreach ($eselon3 as $p)
                    <div wire:key="prio-{{ $p->id }}" class="rounded-xl border border-line p-3">
                        <div class="mb-1.5 text-sm font-semibold">{{ $p->name }} <span class="font-normal text-muted">· {{ $p->holder()?->name ?? 'kosong' }}</span></div>
                        <textarea wire:model="priority.{{ $p->id }}" rows="2" class="field" placeholder="Arahan prioritas untuk {{ $p->name }}"></textarea>
                        <input type="file" wire:model="priorityFiles.{{ $p->id }}" class="mt-2 text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-surface file:px-3 file:py-1.5 file:text-xs file:font-semibold">
                        @error("priorityFiles.$p->id")<p class="error">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </section>

            <button type="submit" class="btn-gold h-12 text-base" wire:loading.attr="disabled">Kirim arahan</button>
        </form>

        <section class="card flex flex-col gap-2 self-start lg:col-span-2">
            <h2 class="card-title">Riwayat arahan</h2>
            @forelse ($history as $d)
                <div wire:key="h-{{ $d->id }}" class="rounded-xl border border-line p-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <span @class(['chip', 'bg-gold-soft text-[#7A5A00]' => $d->type === 'priority', 'bg-info-soft text-navy' => $d->type !== 'priority'])>{{ $d->typeLabel() }}</span>
                        <span class="text-[11px] text-muted">{{ $d->date->format('d/m/Y') }} {{ $d->created_at->format('H.i') }}</span>
                    </div>
                    <p class="mt-1 text-xs">{{ str($d->content)->limit(110) }}</p>
                    <div class="mt-1 text-[11px] text-muted">Oleh {{ $d->fromUser->name }} · {{ $d->recipients->whereNotNull('confirmed_at')->count() }}/{{ $d->recipients->count() }} SIAP</div>
                    <div class="mt-1.5 flex flex-wrap gap-1">
                        @foreach ($d->recipients as $r)
                            <span class="inline-flex items-center gap-0.5"><a href="{{ route('recipient.show', $r) }}" wire:navigate class="chip {{ $r->confirmed_at ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $r->toPosition->name }}</a><x-wa-status :message="$r->whatsappFor()" compact /></span>
                        @endforeach
                    </div>
                </div>
            @empty
                <x-empty>Belum ada arahan.</x-empty>
            @endforelse
            {{ $history->links(data: ['scrollTo' => false]) }}
        </section>
    </main>
</div>
