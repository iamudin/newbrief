<div>
    <x-page-header title="Kepatuhan Jam Instruksi" subtitle="Batas: Eselon 3 ≤ {{ \App\Models\Setting::get('deadline_eselon3') }} · Eselon 4 ≤ {{ \App\Models\Setting::get('deadline_eselon4') }} · Staff ≤ {{ \App\Models\Setting::get('deadline_staff') }}">
        <x-slot:actions><input type="date" wire:model.live="date" max="{{ today()->toDateString() }}" class="rounded-lg border-0 bg-navy-2 px-2 py-1.5 text-xs text-white"></x-slot:actions>
        <div class="mt-4 flex gap-1 rounded-xl bg-navy-2 p-1">
            @foreach (\App\Enums\Level::compliable() as $l)
                <button type="button" wire:click="$set('tab', '{{ $l->value }}')" @class(['tab', 'tab-active' => $tab === $l->value, '!text-[#C9D3E3]' => $tab !== $l->value])>
                    {{ $l->label() }} @if ($counts[$l->value])<span class="ml-1 rounded-full bg-rose-500 px-1.5 text-[10px] text-white">{{ $counts[$l->value] }}</span>@endif
                </button>
            @endforeach
        </div>
    </x-page-header>

    <main class="mx-auto flex w-full max-w-4xl flex-col gap-2 px-4 py-4 lg:px-8">
        @forelse ($rows as $row)
            @php $result = $row['result']; @endphp
            <div wire:key="c-{{ $row['position']->id }}" class="card flex items-center justify-between gap-3 !p-3">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-info-soft text-xs font-extrabold text-navy">{{ $row['user']?->initials() ?? '—' }}</span>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold">{{ $row['user']?->name ?? 'Jabatan kosong' }}</div>
                        <div class="truncate text-[11px] text-muted">{{ $row['position']->name }}{{ $row['position']->parent ? ' · '.$row['position']->parent->name : '' }}</div>
                        <div class="text-[11px] text-muted">Aksi: <b class="text-ink">{{ $result['acted_at']?->format('H.i') ?? '—' }}</b> / batas {{ $deadline }}</div>
                    </div>
                </div>
                <div class="flex shrink-0 flex-col items-end gap-1.5">
                    @if ($result)<span class="chip {{ $result['status']->badgeClass() }}">{{ $result['status']->label() }}</span>@else<span class="chip bg-slate-100 text-slate-500">Tidak dinilai</span>@endif
                    @if ($result && $result['status']->isViolation() && $row['user'] && $isToday)
                        @if ($row['reprimanded'])
                            <span class="text-[10px] font-semibold text-rose-600">Sudah ditegur</span>
                            <x-wa-status :message="$row['reprimand']?->whatsappFor()" compact />
                        @else
                            <button type="button" wire:click="openReprimand({{ $row['position']->id }})" class="btn-danger btn-sm">Teguran</button>
                        @endif
                    @endif
                </div>
            </div>
        @empty
            <x-empty>Belum ada jabatan pada jenjang ini.</x-empty>
        @endforelse
    </main>

    @if ($reprimandPositionId)
        <div class="fixed inset-0 z-[950] flex items-end justify-center bg-black/40 p-3 sm:items-center">
            <form wire:submit="sendReprimand" class="card w-full max-w-md">
                <h2 class="card-title mb-2">Kirim teguran</h2>
                <textarea wire:model="reprimandMessage" rows="4" class="field"></textarea>
                @error('reprimandMessage')<p class="error">{{ $message }}</p>@enderror
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <button type="button" wire:click="$set('reprimandPositionId', null)" class="btn-outline">Batal</button>
                    <button type="submit" class="btn-danger">Kirim teguran</button>
                </div>
            </form>
        </div>
    @endif
</div>
