@php
    use App\Services\KadisDashboardService as K;
    $r = $report;
    $isMonth = $period === 'bulanan';
    $valueQuery = ['periode' => $period, ...($isCurrent ? [] : ['tanggal' => $date])];
@endphp
<div>
    <x-page-header title="Dashboard Akuntabilitas" :subtitle="($isMonth ? 'Evaluasi bulanan · ' : 'Evaluasi mingguan · ').$r['label']">
        @include('livewire.kadis.partials.dashboard-controls', ['isCurrent' => $isCurrent, 'unit' => $isMonth ? 'bulan' : 'minggu'])
    </x-page-header>

    <main class="mx-auto flex w-full max-w-6xl flex-col gap-3 px-4 py-4 lg:px-8" wire:loading.class="opacity-60" wire:target="date,shiftDay,period">
        <section class="card flex flex-col gap-0.5">
            <h2 class="text-base font-bold">{{ $isMonth ? 'Evaluasi Bulanan' : 'Evaluasi Mingguan' }} · {{ $r['label'] }}</h2>
            <span class="text-xs text-muted">
                Rekap otomatis {{ $r['from']->translatedFormat('d M') }}–{{ $r['to']->translatedFormat('d M Y') }} ({{ $r['workdays'] }} hari kerja · {{ $r['people'] }} pegawai · {{ number_format($r['stats']['tasks_total'], 0, ',', '.') }} tugas){{ $isCurrent ? ' · periode berjalan' : '' }}.
                Tidak ada laporan baru yang diminta dari pegawai; semua angka ditarik dari data harian.
            </span>
        </section>

        @if ($r['stats']['rows'] === 0)
            <x-empty>Belum ada hari kerja yang dinilai pada periode ini.</x-empty>
        @else
            <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-3">
                @foreach ($r['cards'] as $c)
                    <div class="card flex flex-col gap-0.5 !p-3.5" data-card="{{ $c['metric'] }}">
                        <span class="text-[11px] font-semibold text-muted">{{ $c['label'] }}</span>
                        <span class="text-2xl font-extrabold {{ K::tone($c['status'], 2) }}">{{ $c['value'] ?? '—' }}<span class="text-sm font-semibold">{{ $c['value'] !== null ? $c['suffix'] : '' }}</span></span>
                        <span class="text-[11px] text-muted">{{ $c['delta'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                {{-- Tren --}}
                <section class="card flex flex-col gap-3" data-test="trend">
                    <h2 class="card-title">Tren kepatuhan & penyelesaian {{ $isMonth ? 'per minggu' : count($r['trend']).' hari kerja' }}</h2>
                    <div class="flex h-44 items-end justify-around gap-2 border-b border-line pb-1">
                        @foreach ($r['trend'] as $t)
                            <div class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1">
                                <span class="text-[10px] font-bold">{{ $t['tuntas'] !== null ? $t['tuntas'].'%' : '—' }}</span>
                                <div class="flex h-full w-full max-w-14 items-end justify-center gap-1">
                                    <div class="w-1/2 rounded-t-md bg-navy-3" style="height: {{ $t['siap'] ?? 0 }}%" title="SIAP {{ $t['siap'] ?? '—' }}%"></div>
                                    <div class="w-1/2 rounded-t-md bg-ok" style="height: {{ $t['tuntas'] ?? 0 }}%" title="Tuntas {{ $t['tuntas'] ?? '—' }}%"></div>
                                </div>
                                <span class="text-[11px] text-muted">{{ $t['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap gap-3 text-[11px] text-muted">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-navy-3"></span>SIAP tepat waktu</span>
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-[3px] bg-ok"></span>Tugas tuntas</span>
                    </div>
                </section>

                @include('livewire.kadis.partials.brief-values', ['values' => $r['values'], 'title' => 'Nilai BRIEF '.($isMonth ? 'bulan ini' : 'minggu ini'), 'valueQuery' => $valueQuery])
            </div>

            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                {{-- Tidak patuh berulang --}}
                <section class="card flex flex-col gap-1" data-test="repeats">
                    <div class="mb-1 flex items-baseline justify-between gap-2">
                        <h2 class="card-title">Tidak patuh berulang (≥ 2 hari)</h2>
                        <span class="chip {{ $r['repeats']->isEmpty() ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $r['repeats']->count() }} orang</span>
                    </div>
                    @forelse ($r['repeats'] as $p)
                        <div class="flex items-start justify-between gap-3 border-t border-line py-2 first-of-type:border-t-0">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold">{{ $p['name'] }}</div>
                                <div class="text-[11px] text-muted">{{ $p['position'] }} · {{ $p['unit'] }}</div>
                                <div class="text-[11px] text-rose-600">{{ $p['detail'] }}</div>
                            </div>
                            <span class="chip shrink-0 {{ $p['days'] >= 3 ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800' }}">{{ $p['days'] }} hari</span>
                        </div>
                    @empty
                        <x-empty>Tidak ada pegawai yang tidak patuh berulang.</x-empty>
                    @endforelse
                    @if ($r['repeats']->isNotEmpty())
                        <a href="{{ route('kadis.compliance') }}" wire:navigate class="btn-outline btn-sm mt-1">Tegur di Kepatuhan Berjenjang</a>
                    @endif
                </section>

                {{-- Rapor per unit --}}
                <section class="card flex flex-col gap-1" data-test="unit-report">
                    <h2 class="card-title mb-1">Rapor capaian per unit</h2>
                    @forelse ($r['units'] as $u)
                        <div class="flex flex-col gap-1.5 border-t border-line py-2 first-of-type:border-t-0">
                            <div class="flex items-center justify-between gap-2">
                                <span class="flex min-w-0 items-center gap-2 text-sm font-semibold"><span class="size-2.5 shrink-0 rounded-full" style="background: {{ K::TONES[$u['status']][0] }}"></span><span class="truncate">{{ $u['name'] }}</span></span>
                                @if ($u['violations'])<span class="shrink-0 text-[11px] text-rose-600">{{ $u['violations'] }} pelanggaran</span>@endif
                            </div>
                            <div class="grid grid-cols-3 gap-1.5 text-center text-[11px]">
                                @foreach (['siap' => 'SIAP', 'lapor' => 'Lapor', 'tuntas' => 'Tuntas'] as $k => $label)
                                    <span class="rounded-lg py-1 {{ K::tone(K::status($u[$k], K::target($k))) }}">{{ $label }} <b>{{ $u[$k] !== null ? $u[$k].'%' : '—' }}</b></span>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <x-empty>Belum ada unit yang dinilai.</x-empty>
                    @endforelse
                </section>
            </div>

            <section class="card flex flex-col gap-1 border border-[#C9D3E3] bg-info-soft/60" data-test="decision">
                <span class="text-xs font-bold tracking-wide text-navy uppercase">Tindak lanjut pimpinan</span>
                <p class="text-sm">{{ $r['decision'] }}</p>
            </section>
        @endif
    </main>
</div>
