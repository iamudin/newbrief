<div>
    <x-page-header title="Anggota" :subtitle="$isEselon3 ? 'Bawahan langsung & bawahan dari bawahan' : 'Bawahan langsung'" />

    <main class="mx-auto grid w-full max-w-6xl gap-3 px-4 py-4 lg:grid-cols-3 lg:px-8">
        <div class="flex flex-col gap-2 lg:col-span-2">
            @forelse ($rows as $row)
                @php $p = $row['position']; $c = $row['compliance']; @endphp
                <div wire:key="m-{{ $p->id }}" class="card !p-3">
                    <div class="flex items-center gap-3">
                        <span @class(['flex size-10 shrink-0 items-center justify-center rounded-full text-xs font-extrabold', 'bg-navy text-gold' => $p->level === \App\Enums\Level::Eselon4, 'bg-info-soft text-navy' => $p->level !== \App\Enums\Level::Eselon4])>{{ $row['user']?->initials() ?? '—' }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold">{{ $row['user']?->name ?? 'Jabatan kosong' }}</div>
                            <div class="truncate text-[11px] text-muted">{{ $p->name }}@if ($isEselon3 && $p->level === \App\Enums\Level::Staff) · di bawah {{ $p->parent?->name }}@endif</div>
                            <div class="mt-1 flex items-center gap-2"><div class="w-20"><x-progress :percent="$row['tasks_percent']" /></div><span class="text-[11px] font-semibold">{{ $row['tasks_done'] }}/{{ $row['tasks_total'] }} ({{ $row['tasks_percent'] }}%)</span></div>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            @if ($c)<span class="chip {{ $c['status']->badgeClass() }}">{{ $c['status']->label() }}{{ $c['acted_at'] ? ' '.$c['acted_at']->format('H.i') : '' }}</span>@endif
                            <span class="chip {{ \App\Models\LocationLog::zoneClass($row['location']?->zone) }}">{{ \App\Models\LocationLog::zoneLabel($row['location']?->zone) }}</span>
                        </div>
                    </div>
                    @if ($row['user'])
                        <div class="mt-2.5 flex flex-wrap gap-1.5 border-t border-line pt-2.5">
                            @if ($row['field'])
                                <span class="chip bg-sky-100 text-sky-700">Dinas luar{{ $row['field']->destination ? ': '.$row['field']->destination : '' }}</span>
                                <button type="button" wire:click="clearField({{ $p->id }})" class="text-[11px] font-semibold text-muted underline">batalkan</button>
                            @else
                                <button type="button" wire:click="openField({{ $p->id }})" class="btn-outline btn-sm">Tetapkan dinas luar</button>
                            @endif
                            @if ($c && $c['status']->isViolation())
                                @if ($row['reprimanded'])<span class="chip bg-rose-50 text-rose-600">Sudah ditegur</span>@else<button type="button" wire:click="openReprimand({{ $p->id }})" class="btn-danger btn-sm">Teguran</button>@endif
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <x-empty>Belum ada anggota di struktur Anda.</x-empty>
            @endforelse
        </div>

        <section class="card flex flex-col gap-2 self-start">
            <h2 class="card-title">Persetujuan tugas inisiatif</h2>
            @forelse ($pending as $ini)
                <div wire:key="ini-{{ $ini->id }}" class="rounded-xl bg-gold-soft/60 p-3">
                    <div class="text-sm font-semibold">{{ $ini->title }}</div>
                    @if ($ini->description)<div class="text-xs text-muted">{{ $ini->description }}</div>@endif
                    <div class="mt-0.5 text-[11px] text-muted">{{ $ini->user->name }} · {{ $ini->position->name }}</div>
                    <input type="text" wire:model="reviewNotes.{{ $ini->id }}" class="field mt-2 !py-1.5 text-xs" placeholder="Catatan (opsional)">
                    <div class="mt-2 grid grid-cols-2 gap-1.5">
                        <button type="button" wire:click="review({{ $ini->id }}, false)" class="btn-outline btn-sm">Tolak</button>
                        <button type="button" wire:click="review({{ $ini->id }}, true)" class="btn-ok btn-sm">Setujui</button>
                    </div>
                </div>
            @empty
                <x-empty>Tidak ada usulan inisiatif dari bawahan langsung.</x-empty>
            @endforelse
        </section>
    </main>

    @if ($fieldPositionId)
        <div class="fixed inset-0 z-[950] flex items-end justify-center bg-black/40 p-3 sm:items-center">
            <form wire:submit="saveField" class="card w-full max-w-md">
                <h2 class="card-title mb-2">Tetapkan dinas luar hari ini</h2>
                <input type="text" wire:model="destination" class="field" placeholder="Tujuan / lokasi (opsional)">
                <p class="mt-1 text-[11px] text-muted">Lokasi di luar radius kantor akan dianggap dinas luar, bukan pelanggaran.</p>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <button type="button" wire:click="$set('fieldPositionId', null)" class="btn-outline">Batal</button>
                    <button type="submit" class="btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    @endif

    @if ($reprimandPositionId)
        <div class="fixed inset-0 z-[950] flex items-end justify-center bg-black/40 p-3 sm:items-center">
            <form wire:submit="sendReprimand" class="card w-full max-w-md">
                <h2 class="card-title mb-2">Kirim teguran</h2>
                <textarea wire:model="reprimandMessage" rows="4" class="field"></textarea>
                @error('reprimandMessage')<p class="error">{{ $message }}</p>@enderror
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <button type="button" wire:click="$set('reprimandPositionId', null)" class="btn-outline">Batal</button>
                    <button type="submit" class="btn-danger">Kirim</button>
                </div>
            </form>
        </div>
    @endif
</div>
