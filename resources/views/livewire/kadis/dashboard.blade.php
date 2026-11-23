@php
    use App\Services\KadisDashboardService as K;
    $q = $data['is_today'] ? [] : ['tanggal' => $data['date']->toDateString()];
    $metric = fn (string $key) => route('kadis.metric', ['metric' => $key, ...$q]);
@endphp
<div>
    <x-page-header title="Dashboard Akuntabilitas" :subtitle="$data['date']->translatedFormat('l, d F Y').($data['is_today'] ? ' · '.now()->format('H.i') : '')">
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <span class="text-xs text-[#C9D3E3]">Ketuk angka atau grafik untuk melihat sumber datanya</span>
            <div class="flex items-center gap-1 rounded-xl bg-navy-2 p-1 text-xs font-semibold">
                <button type="button" wire:click="shiftDay(-1)" class="rounded-lg px-2.5 py-1.5 hover:bg-white/10" aria-label="Hari kerja sebelumnya">‹</button>
                <input type="date" wire:model.live="date" max="{{ today()->toDateString() }}" class="w-[8.5rem] rounded-lg bg-transparent px-1 py-1 text-center text-white [color-scheme:dark]" aria-label="Pilih tanggal">
                <button type="button" wire:click="shiftDay(1)" @disabled($data['is_today']) class="rounded-lg px-2.5 py-1.5 hover:bg-white/10 disabled:opacity-30" aria-label="Hari kerja berikutnya">›</button>
                @unless ($data['is_today'])
                    <button type="button" wire:click="$set('date', '{{ today()->toDateString() }}')" class="rounded-lg bg-white px-2.5 py-1.5 text-navy">Hari ini</button>
                @endunless
            </div>
        </div>
    </x-page-header>

    <main class="mx-auto flex w-full max-w-6xl flex-col gap-3 px-4 py-4 lg:px-8" wire:loading.class="opacity-60" wire:target="date,shiftDay">
        @unless ($data['workday'])
            <div class="card flex items-center gap-3 border border-amber-200 bg-amber-50 text-sm text-amber-800">
                <x-icon name="clock" />
                <span><b>{{ $data['date']->translatedFormat('l, d F Y') }}</b> adalah akhir pekan atau hari libur, jadi kepatuhan tidak dinilai.</span>
            </div>
        @endunless

        {{-- Arahan berjenjang --}}
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <a href="{{ route('kadis.compliance') }}" wire:navigate class="card flex items-center gap-3 border border-line hover:border-navy-3" data-test="cascade">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $s['cascade_total'] && $s['cascade_ok'] === $s['cascade_total'] ? 'bg-ok-soft text-ok' : 'bg-gold-soft text-[#7A5A00]' }}"><x-icon name="clock" /></span>
                <span class="flex min-w-0 flex-1 flex-col">
                    <span class="text-sm font-bold">Arahan berjenjang: {{ $s['cascade_ok'] }} dari {{ $s['cascade_total'] }} tepat waktu</span>
                    <span class="text-[11px] text-muted">
                        @foreach ($s['levels'] as $l){{ $l['label'] }} {{ $l['patuh'] }}/{{ $l['total'] }} (≤ {{ $l['deadline'] }})@if (! $loop->last) · @endif @endforeach · tegur atau arahkan
                    </span>
                </span>
                <x-icon name="chevron" size="18" class="text-muted" />
            </a>
            @php $k = $data['kadis']; @endphp
            <a href="{{ route('kadis.directions') }}" wire:navigate class="card flex items-center gap-3 border border-line hover:border-navy-3" data-test="kadis-direction">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $k['sent_at'] ? ($k['on_time'] ? 'bg-ok-soft text-ok' : 'bg-gold-soft text-[#7A5A00]') : 'bg-rose-50 text-rose-600' }}"><x-icon name="pen" /></span>
                <span class="flex min-w-0 flex-1 flex-col">
                    @if ($k['sent_at'])
                        <span class="text-sm font-bold">Arahan Anda terkirim {{ $k['sent_at']->format('H.i') }} {{ $k['on_time'] ? '' : '(lewat '.$k['deadline'].')' }}</span>
                        <span class="text-[11px] text-muted">{{ $k['count'] }} arahan{{ $k['priority'] ? ' · '.$k['priority'].' prioritas' : '' }} · {{ $k['confirmed'] }}/{{ $k['recipients'] }} pejabat SIAP</span>
                    @else
                        <span class="text-sm font-bold">{{ $data['is_today'] ? 'Anda belum mengirim arahan hari ini' : 'Tidak ada arahan pada tanggal ini' }}</span>
                        <span class="text-[11px] text-muted">Disarankan sebelum {{ $k['deadline'] }} · tupoksi tetap berjalan berjenjang</span>
                    @endif
                </span>
                <x-icon name="chevron" size="18" class="text-muted" />
            </a>
        </div>

        {{-- Angka utama --}}
        <section class="card flex flex-col gap-2.5">
            <h2 class="text-xs font-bold tracking-wide text-muted uppercase">{{ $s['total'] }} pegawai {{ $data['is_today'] ? 'hari ini' : 'pada tanggal ini' }}</h2>
            <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-4">
                @php
                    $tiles = [
                        ['siap', 'Konfirmasi SIAP', $s['acted'], ' / '.$s['total'], (K::pct($s['acted'], $s['total']) ?? 0).'% · '.$s['patuh'].' tepat waktu', 'text-navy'],
                        ['lapor', 'Sudah melapor', $s['reported_people'], ' orang', $s['tasks_done'].' tugas tuntas', 'text-navy'],
                        ['patuh', 'Tidak patuh', $s['non_compliant']->count(), ' orang', $data['provisional'] ? 'sementara · evaluasi '.str_replace(':', '.', \App\Models\Setting::get('verify_deadline')) : 'hasil evaluasi '.str_replace(':', '.', \App\Models\Setting::get('verify_deadline')), 'text-rose-600'],
                        ['inisiatif', 'Tugas inisiatif', $s['initiatives'], '', $s['initiatives_pending'].' menunggu persetujuan', 'text-[#7A5A00]'],
                    ];
                @endphp
                @foreach ($tiles as [$key, $label, $value, $suffix, $hint, $color])
                    <a href="{{ $metric($key) }}" wire:navigate class="flex flex-col gap-0.5 rounded-xl border border-line bg-[#FAFBFD] p-3 hover:border-navy-3" data-metric="{{ $key }}">
                        <span class="text-[11px] font-semibold text-muted">{{ $label }}</span>
                        <span class="text-2xl font-extrabold {{ $color }}">{{ $value }}<span class="text-sm font-semibold text-muted">{{ $suffix }}</span></span>
                        <span class="text-[11px] text-muted">{{ $hint }} · lihat sumber</span>
                    </a>
                @endforeach
            </div>
        </section>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            {{-- Evaluasi kinerja kantor --}}
            <section class="card flex flex-col gap-3">
                <div class="flex flex-wrap items-baseline justify-between gap-1">
                    <h2 class="card-title">Evaluasi kinerja kantor</h2>
                    <span class="text-xs text-muted"><b class="text-ink">{{ $s['achieved'] }} dari {{ $s['measured'] }} indikator</b> mencapai target</span>
                </div>
                <div class="grid grid-cols-2 gap-2.5">
                    @foreach ($s['donuts'] as $key => $d)
                        <a href="{{ $metric($key) }}" wire:navigate class="flex flex-col items-center gap-1 rounded-xl border border-line bg-[#FAFBFD] p-3 text-center hover:border-navy-3" data-metric="{{ $key }}">
                            <x-donut :pct="$d['pct']" :target="$d['target']" :status="$d['status']" />
                            <span class="text-xs font-bold">{{ $d['title'] }}</span>
                            <span class="text-[11px] font-semibold {{ K::tone($d['status'], 2) }}">{{ $d['actual'] }}</span>
                            <span class="text-[10px] text-muted">{{ $d['target_label'] }} · rincian</span>
                        </a>
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-3 text-[11px] text-muted">
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-ok"></span>Tercapai</span>
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-gold"></span>Sedikit di bawah target</span>
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-[#B3261E]"></span>Jauh di bawah target</span>
                </div>
            </section>

            <div class="flex flex-col gap-3">
                {{-- Posisi pegawai --}}
                @php $zoneTotal = max(1, $s['total']); @endphp
                <a href="{{ $metric('lokasi') }}" wire:navigate class="card flex flex-col gap-2.5 border border-line hover:border-navy-3" data-metric="lokasi">
                    <span class="flex items-baseline justify-between gap-2">
                        <span class="card-title">Posisi saat SIAP dan melapor</span>
                        <span class="text-[11px] text-muted">{{ $s['total'] }} orang · lihat sumber</span>
                    </span>
                    <span class="flex h-3 overflow-hidden rounded-full bg-line">
                        @foreach (K::ZONES as $zone => [$label, $hex])
                            @if ($s['zones'][$zone])<span style="width: {{ $s['zones'][$zone] / $zoneTotal * 100 }}%; background: {{ $hex }}"></span>@endif
                        @endforeach
                    </span>
                    <span class="grid grid-cols-2 gap-x-3 gap-y-1 text-[11px] text-muted">
                        @foreach (K::ZONES as $zone => [$label, $hex])
                            <span class="flex items-center gap-1.5"><span class="size-2.5 shrink-0 rounded-full" style="background: {{ $hex }}"></span>{{ $label }} <b class="text-ink">{{ $s['zones'][$zone] }}</b></span>
                        @endforeach
                    </span>
                </a>

                {{-- Peta beban kerja --}}
                @php $w = $s['workload']; @endphp
                <a href="{{ $metric('merata') }}" wire:navigate class="card flex flex-col gap-2 border border-line hover:border-navy-3" data-metric="merata">
                    <span class="card-title">Peta beban kerja</span>
                    <span class="text-xs text-muted">
                        @if ($w['staff'] && $w['heavy_people'])
                            <b class="text-ink">{{ $w['heavy_people'] }} staf memegang {{ $w['heavy_share'] }}% tugas</b>, sementara {{ $w['zero'] }} staf belum mendapat tugas.
                        @elseif ($w['staff'])
                            <b class="text-ink">{{ $w['ideal'] }} dari {{ $w['staff'] }} staf</b> berada di beban ideal {{ $w['min'] }}–{{ $w['max'] }} tugas{{ $w['zero'] ? ', '.$w['zero'].' staf belum mendapat tugas' : '' }}.
                        @else
                            Belum ada staf yang dinilai.
                        @endif
                    </span>
                    <span class="text-[11px] text-muted">Jumlah staf menurut tugas per orang · lihat sumber</span>
                    <span class="flex flex-col gap-1.5">
                        @foreach ($w['buckets'] as $b)
                            <span class="grid grid-cols-[7.5rem_1fr_3rem] items-center gap-2 text-[11px]">
                                <span class="truncate text-muted">{{ $b['label'] }}</span>
                                <span class="h-2.5 overflow-hidden rounded-full bg-line"><span class="block h-full rounded-full" style="width: {{ $b['bar'] }}%; background: {{ K::TONES[$b['status']][0] }}"></span></span>
                                <span class="text-right font-bold">{{ $b['people'] }} org</span>
                            </span>
                        @endforeach
                    </span>
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            {{-- Tidak patuh --}}
            <section class="card flex flex-col gap-2.5" data-test="non-compliant">
                <div class="flex items-baseline justify-between gap-2">
                    <h2 class="card-title">Tidak patuh {{ $data['provisional'] ? 'sementara' : 'sampai '.str_replace(':', '.', \App\Models\Setting::get('verify_deadline')) }}</h2>
                    <span class="chip {{ $s['non_compliant']->isEmpty() ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $s['non_compliant']->count() }} orang</span>
                </div>
                @if ($s['non_compliant']->isEmpty())
                    <x-empty>Tidak ada pegawai tidak patuh.</x-empty>
                @else
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($s['non_compliant_units'] as $unit => $n)
                            <span class="chip bg-rose-50 text-rose-700">{{ $unit }} · {{ $n }}</span>
                        @endforeach
                    </div>
                    <div class="flex flex-col divide-y divide-line">
                        @foreach ($s['non_compliant']->take(5) as $p)
                            <div class="flex items-start justify-between gap-2 py-2">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold">{{ $p['name'] }}</div>
                                    <div class="truncate text-[11px] text-muted">{{ $p['position'] }} · {{ $p['unit'] }}</div>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-0.5 text-right">
                                    <span class="max-w-[11rem] text-[11px] font-semibold text-rose-600">{{ $p['issues'][0] }}@if (count($p['issues']) > 1) <span class="text-muted">+{{ count($p['issues']) - 1 }}</span>@endif</span>
                                    @if ($p['reminded_at'])<span class="text-[10px] text-muted">Diingatkan {{ $p['reminded_at'] }}</span>@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if ($s['non_compliant']->count() > 5)
                        <span class="text-[11px] text-muted">+ {{ $s['non_compliant']->count() - 5 }} pegawai lainnya</span>
                    @endif
                    <div class="grid grid-cols-2 gap-2">
                        @if ($data['is_today'])
                            <button type="button" wire:click="sendReminders" wire:confirm="Kirim pengingat (notifikasi + WhatsApp) ke {{ $s['to_remind'] }} pegawai tidak patuh?" @disabled(! $s['to_remind']) wire:loading.attr="disabled" class="btn-danger btn-sm" data-test="remind">
                                {{ $s['to_remind'] ? 'Kirim pengingat ke '.$s['to_remind'] : 'Semua sudah diingatkan' }}
                            </button>
                        @endif
                        <a href="{{ $metric('patuh') }}" wire:navigate class="btn-outline btn-sm {{ $data['is_today'] ? '' : 'col-span-2' }}">Lihat semua & penyebab</a>
                    </div>
                @endif
            </section>

            {{-- Penyelesaian per unit --}}
            <a href="{{ $metric('tuntas') }}" wire:navigate class="card flex flex-col gap-2.5 self-start border border-line hover:border-navy-3" data-metric="tuntas">
                <span class="flex items-baseline justify-between gap-2">
                    <span class="card-title">Penyelesaian tugas per unit</span>
                    <span class="text-[11px] text-muted">{{ $data['is_today'] ? 'hari ini' : $data['date']->translatedFormat('d M') }} · lihat sumber</span>
                </span>
                @forelse ($s['unit_tasks'] as $u)
                    <span class="flex flex-col gap-1">
                        <span class="flex justify-between gap-2 text-xs"><span class="truncate font-semibold">{{ $u['name'] }}</span><span class="shrink-0 text-muted"><b class="text-ink">{{ $u['done'] }}/{{ $u['total'] }}</b>{{ $u['pct'] !== null ? ' · '.$u['pct'].'%' : '' }}</span></span>
                        <span class="h-2 overflow-hidden rounded-full bg-line"><span class="block h-full rounded-full" style="width: {{ $u['pct'] ?? 0 }}%; background: {{ K::TONES[$u['status']][0] }}"></span></span>
                    </span>
                @empty
                    <span class="text-xs text-muted">Belum ada unit (Eselon 3) di struktur organisasi.</span>
                @endforelse
            </a>
        </div>
    </main>
</div>
