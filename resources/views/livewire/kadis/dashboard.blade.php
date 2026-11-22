<div>
    <x-page-header title="Dashboard Kepala Dinas">
        <div class="mt-4"><x-period-filter /></div>
    </x-page-header>

    <main class="mx-auto flex w-full max-w-6xl flex-col gap-3 px-4 py-4 lg:px-8">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <x-stat label="Eselon 3 SIAP" :value="$siapCount.' / '.$siapTotal" :hint="'konfirmasi atas arahan Anda ('.$eselon3Count.' Eselon 3)'" tone="ok" />
            <x-stat label="Sudah melapor" :value="$reported.' / '.$recipientsTotal" :hint="$verified.' laporan diterima atasan'" />
            <x-stat label="Tidak patuh" :value="$summary['tidak'] + $summary['terlambat']" :hint="$summary['tidak'].' tidak aksi · '.$summary['terlambat'].' terlambat'" tone="bad" />
            <x-stat label="Inisiatif menunggu" :value="$pendingInitiatives->count()" hint="usulan menunggu persetujuan atasan" tone="gold" />
        </div>

        <div class="grid gap-3 lg:grid-cols-3">
            <section class="card flex flex-col gap-3 lg:col-span-1">
                <h2 class="card-title">Kepatuhan per jenjang</h2>
                @foreach ($byLevel as $row)
                    @php $pct = $row['total'] ? round($row['patuh'] / $row['total'] * 100) : 0; @endphp
                    <div class="flex flex-col gap-1">
                        <div class="flex justify-between text-xs"><span class="font-semibold">{{ $row['label'] }}</span><span class="text-muted">{{ $row['patuh'] }}/{{ $row['total'] }} · <b class="text-ink">{{ $pct }}%</b></span></div>
                        <x-progress :percent="$pct" :tone="$pct >= 90 ? 'bg-ok' : ($pct >= 70 ? 'bg-gold' : 'bg-rose-500')" />
                    </div>
                @endforeach
                <a href="{{ route('kadis.compliance') }}" wire:navigate class="btn-outline btn-sm mt-1">Lihat detail kepatuhan</a>
            </section>

            <section class="card flex flex-col gap-2 lg:col-span-2">
                <div class="flex items-center justify-between">
                    <h2 class="card-title">Arahan & konfirmasi SIAP</h2>
                    <a href="{{ route('kadis.directions') }}" wire:navigate class="text-xs font-bold text-navy">+ Arahan baru</a>
                </div>
                @forelse ($directions as $direction)
                    @php $ok = $direction->recipients->whereNotNull('confirmed_at')->count(); @endphp
                    <div class="rounded-xl border border-line p-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span @class(['chip', 'bg-gold-soft text-[#7A5A00]' => $direction->type === 'priority', 'bg-info-soft text-navy' => $direction->type !== 'priority'])>{{ $direction->typeLabel() }}</span>
                                <span class="ml-1 text-[11px] text-muted">{{ $direction->date->translatedFormat('D, d M') }} · {{ $direction->created_at->format('H.i') }}</span>
                                <p class="mt-1 text-sm">{{ str($direction->content)->limit(120) }}</p>
                            </div>
                            <span class="chip shrink-0 {{ $ok === $direction->recipients->count() ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' }}">{{ $ok }}/{{ $direction->recipients->count() }} SIAP</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($direction->recipients as $r)
                                <a href="{{ route('recipient.show', $r) }}" wire:navigate class="chip {{ $r->confirmed_at ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $r->toPosition->name }} {{ $r->confirmed_at ? '· '.$r->confirmed_at->format('H.i') : '' }}</a>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <x-empty>Belum ada arahan pada periode ini.</x-empty>
                @endforelse
            </section>
        </div>

        <section class="card flex flex-col gap-2">
            <h2 class="card-title">Tugas inisiatif menunggu persetujuan</h2>
            @forelse ($pendingInitiatives as $initiative)
                <div class="flex items-center justify-between gap-2 rounded-xl bg-gold-soft/60 p-2.5">
                    <div><div class="text-sm font-semibold">{{ $initiative->title }}</div><div class="text-[11px] text-muted">{{ $initiative->user->name }} · {{ $initiative->position->name }} · {{ $initiative->created_at->diffForHumans() }}</div></div>
                    <span class="chip bg-amber-100 text-amber-800">Menunggu atasan</span>
                </div>
            @empty
                <x-empty>Tidak ada usulan inisiatif yang menunggu.</x-empty>
            @endforelse
        </section>
    </main>
</div>
