<div>
    <x-page-header title="Daftar Pegawai" subtitle="Tugas & kepatuhan hari ini · {{ now()->translatedFormat('d F Y') }}">
        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama / jabatan…" class="field !border-0 sm:max-w-xs">
            <select wire:model.live="level" class="field !border-0 sm:max-w-[180px]">
                <option value="">Semua jenjang</option>
                @foreach (\App\Enums\Level::compliable() as $l)<option value="{{ $l->value }}">{{ $l->label() }}</option>@endforeach
            </select>
        </div>
    </x-page-header>

    <main class="mx-auto flex w-full max-w-5xl flex-col gap-2 px-4 py-4 lg:px-8">
        @forelse ($rows as $row)
            <div wire:key="e-{{ $row['position']->id }}" class="card !p-0">
                <button type="button" wire:click="toggle({{ $row['position']->id }})" class="flex w-full cursor-pointer items-center gap-3 p-3 text-left">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-info-soft text-xs font-extrabold text-navy">{{ $row['user']?->initials() ?? '—' }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="truncate text-sm font-semibold">{{ $row['user']?->name ?? 'Jabatan kosong' }}</span>
                            <span class="chip bg-surface text-muted">{{ $row['position']->level->label() }}</span>
                        </div>
                        <div class="truncate text-[11px] text-muted">{{ $row['position']->name }}{{ $row['position']->unit ? ' · '.$row['position']->unit : '' }}</div>
                        <div class="mt-1.5 flex items-center gap-2">
                            <div class="w-24"><x-progress :percent="$row['tasks_percent']" /></div>
                            <span class="text-[11px] font-semibold">{{ $row['tasks_done'] }}/{{ $row['tasks_total'] }} tugas ({{ $row['tasks_percent'] }}%)</span>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        @if ($row['compliance'])<span class="chip {{ $row['compliance']['status']->badgeClass() }}">{{ $row['compliance']['status']->label() }}</span>@endif
                        <span class="chip {{ \App\Models\LocationLog::zoneClass($row['location']?->zone) }}">{{ \App\Models\LocationLog::zoneLabel($row['location']?->zone) }}</span>
                    </div>
                </button>
                @if ($expanded === $row['position']->id)
                    <div class="border-t border-line p-3">
                        @forelse ($detail ?? [] as $r)
                            <a href="{{ route('recipient.show', $r) }}" wire:navigate class="mb-1.5 flex items-center justify-between gap-2 rounded-lg bg-surface p-2 text-xs">
                                <span>{{ $r->direction->typeLabel() }} dari {{ $r->direction->fromUser->name }} · {{ $r->doneCount() }}/{{ $r->tasks->count() }}</span>
                                <span class="chip {{ $r->stateClass() }}">{{ $r->stateLabel() }}</span>
                            </a>
                        @empty
                            <x-empty>Belum ada instruksi masuk hari ini.</x-empty>
                        @endforelse
                    </div>
                @endif
            </div>
        @empty
            <x-empty>Tidak ada pegawai yang cocok.</x-empty>
        @endforelse
    </main>
</div>
