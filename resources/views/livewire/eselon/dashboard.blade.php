<div>
    <x-page-header :title="'Selamat bekerja, '.str(auth()->user()->name)->before(',')">
        <div class="mt-1 text-xs text-[#C9D3E3]">{{ $position->name }}{{ $position->unit ? ' · '.$position->unit : '' }}</div>
        <div class="mt-4"><x-period-filter /></div>
    </x-page-header>

    <main class="mx-auto grid w-full max-w-6xl gap-3 px-4 py-4 lg:grid-cols-3 lg:px-8">
        <div class="flex flex-col gap-3 lg:col-span-1">
            <x-checkin-card :checkin="$checkin" :compliance="$todayCompliance" :deadline="$deadline" />
            <div class="grid grid-cols-2 gap-3">
                <x-stat label="Instruksi masuk" :value="$stats['received']" :hint="$stats['confirmed'].' dikonfirmasi SIAP'" />
                <x-stat label="Hari patuh" :value="$summary['patuh'].' / '.($summary['total'] - $summary['menunggu'])" :hint="$summary['terlambat'].' terlambat · '.$summary['tidak'].' tidak'" tone="ok" />
                @if (! $isEselon3)
                    <x-stat label="Tugas saya" :value="$stats['my_done'].' / '.$stats['my_total']" hint="selesai dari atasan" />
                @endif
                <x-stat label="Tugas tim selesai" :value="$stats['team_done'].' / '.$stats['team_total']" :hint="$stats['team_pending'].' belum selesai'" tone="ok" />
                <x-stat label="Laporan tim" :value="$stats['team_verified'].' / '.$stats['team_reported']" hint="diterima / masuk" tone="gold" />
            </div>
            <x-initiative-card :initiatives="$initiatives" />
        </div>

        <section class="card flex flex-col gap-2 self-start lg:col-span-2">
            <h2 class="card-title">Riwayat instruksi {{ $isEselon3 ? 'Kepala Dinas' : 'Eselon 3' }}</h2>
            <p class="-mt-1 text-[11px] text-muted">Riwayat melekat pada jabatan, termasuk milik pejabat sebelumnya.</p>
            @forelse ($history as $r)
                <div role="link" tabindex="0" x-data x-on:click="Livewire.navigate('{{ route('recipient.show', $r) }}')" wire:key="hist-{{ $r->id }}" class="cursor-pointer rounded-xl border border-line p-3 hover:bg-surface">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-1.5">
                            <span @class(['chip', 'bg-gold-soft text-[#7A5A00]' => $r->direction->type === 'priority', 'bg-info-soft text-navy' => $r->direction->type !== 'priority'])>{{ $r->direction->typeLabel() }}</span>
                            <span class="text-[11px] text-muted">{{ $r->direction->date->translatedFormat('D, d M Y') }} · {{ $r->direction->created_at->format('H.i') }}</span>
                        </div>
                        <span class="chip {{ $r->stateClass() }}">{{ $r->stateLabel() }}</span>
                    </div>
                    <p class="mt-1.5 text-sm">{{ str($r->direction->content)->limit(150) }}</p>
                    <div class="mt-1 text-[11px] text-muted">Dari {{ $r->direction->fromUser->name }} · SIAP {{ $r->confirmed_at?->format('H.i') ?? '—' }}@if ($r->tasks->isNotEmpty()) · {{ $r->doneCount() }}/{{ $r->tasks->count() }} tugas @endif @if ($r->to_user_id && $r->to_user_id !== auth()->id()) · <i>pejabat sebelumnya: {{ $r->toUser?->name }}</i>@endif</div>
                    <x-recipient-attachments :recipient="$r" />
                </div>
            @empty
                <x-empty>Belum ada instruksi pada periode ini.</x-empty>
            @endforelse
            {{ $history->links(data: ['scrollTo' => false]) }}
        </section>
    </main>
</div>
