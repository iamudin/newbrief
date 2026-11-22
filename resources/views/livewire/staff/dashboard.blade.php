<div>
    <x-page-header :title="'Selamat pagi, '.str(auth()->user()->name)->before(',')">
        <div class="mt-1 text-xs text-[#C9D3E3]">{{ $position->name }}{{ $position->unit ? ' · '.$position->unit : '' }}</div>
    </x-page-header>

    <main class="mx-auto grid w-full max-w-5xl gap-3 px-4 py-4 lg:grid-cols-2 lg:px-8">
        <div class="flex flex-col gap-3">
            <x-checkin-card :checkin="$checkin" :compliance="$todayCompliance" :deadline="$deadline" />

            @if ($latest)
                <a href="{{ route('recipient.show', $latest) }}" wire:navigate class="flex flex-col gap-1.5 rounded-[14px] bg-navy p-4 text-white">
                    <span class="text-[11px] font-bold tracking-wider text-gold uppercase">Dari {{ $latest->direction->fromPosition->name }} · {{ $latest->direction->created_at->format('H.i') }}</span>
                    <p class="text-[15px] leading-relaxed font-medium">{{ str($latest->direction->content)->limit(180) }}</p>
                    @if ($latest->direction->parent)<span class="text-xs text-[#C9D3E3]">Pemicu: {{ str($latest->direction->parent->content)->limit(100) }}</span>@endif
                </a>
            @endif

            <x-period-filter />
            <div class="grid grid-cols-2 gap-3">
                <x-stat label="Tugas selesai" :value="$stats['done'].' / '.$stats['total']" :hint="$stats['pending'].' belum selesai'" tone="ok" />
                <x-stat label="Instruksi masuk" :value="$stats['received']" :hint="$stats['reported'].' sudah dilaporkan'" />
                <x-stat label="Laporan diterima" :value="$stats['approved']" hint="diverifikasi atasan" tone="gold" />
                <x-stat label="Hari patuh" :value="$summary['patuh'].' / '.($summary['total'] - $summary['menunggu'])" :hint="'SIAP ≤ '.$deadline" tone="ok" />
            </div>
        </div>

        <section class="card flex flex-col gap-2 self-start">
            <h2 class="card-title">Riwayat instruksi</h2>
            @forelse ($history as $r)
                <div role="link" tabindex="0" x-data x-on:click="Livewire.navigate('{{ route('recipient.show', $r) }}')" wire:key="sh-{{ $r->id }}" class="cursor-pointer rounded-xl border border-line p-3 hover:bg-surface">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[11px] text-muted">{{ $r->direction->date->translatedFormat('D, d M') }} · {{ $r->direction->fromUser->name }}</span>
                        <span class="chip {{ $r->stateClass() }}">{{ $r->stateLabel() }}</span>
                    </div>
                    <p class="mt-1 text-sm">{{ str($r->direction->content)->limit(110) }}</p>
                    <div class="mt-1.5 flex items-center gap-2"><div class="w-24"><x-progress :percent="$r->progressPercent()" /></div><span class="text-[11px] font-semibold">{{ $r->doneCount() }}/{{ $r->tasks->count() }}</span></div>
                    <x-recipient-attachments :recipient="$r" />
                </div>
            @empty
                <x-empty>Belum ada instruksi pada periode ini.</x-empty>
            @endforelse
            {{ $history->links(data: ['scrollTo' => false]) }}
        </section>
    </main>
</div>
