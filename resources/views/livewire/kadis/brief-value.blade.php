@php
    use App\Services\KadisDashboardService as K;
    $q = ['periode' => $period, ...($isCurrent ? [] : ['tanggal' => $date])];
    $color = K::TONES[$v['status']][0];
@endphp
<div>
    <x-page-header :title="'Nilai '.$v['key'].' · '.$v['name']" :subtitle="'Nilai BRIEF · '.$label" :back="route('kadis.dashboard', $q)">
        <div class="mt-3 grid grid-cols-5 gap-1.5">
            @foreach ($values as $item)
                <a href="{{ route('kadis.value', ['value' => $item['key'], ...$q]) }}" wire:navigate @class(['flex flex-col items-center rounded-xl py-1.5', 'bg-white text-navy' => $item['key'] === $value, 'bg-navy-2 text-[#C9D3E3]' => $item['key'] !== $value])>
                    <span class="text-sm font-extrabold">{{ $item['key'] }}</span>
                    <span class="text-xs font-bold">{{ $item['score'] ?? '—' }}</span>
                </a>
            @endforeach
        </div>
    </x-page-header>

    <main class="mx-auto flex w-full max-w-4xl flex-col gap-3 px-4 py-4 lg:px-8">
        <section class="card flex items-center gap-4">
            <span class="flex size-16 shrink-0 flex-col items-center justify-center rounded-2xl text-white" style="background: {{ $color }}">
                <span class="text-2xl leading-none font-extrabold">{{ $v['key'] }}</span>
            </span>
            <div class="flex min-w-0 flex-col">
                <span class="text-3xl font-extrabold" style="color: {{ $color }}" data-test="value-score">{{ $v['score'] ?? '—' }}<span class="text-sm text-muted"> / 100</span></span>
                <span class="text-sm">{{ $v['meaning'] }}</span>
            </div>
        </section>

        <section class="card flex flex-col gap-1">
            <h2 class="card-title mb-1">Asal skor</h2>
            @foreach ($v['parts'] as $part)
                <div class="flex flex-col gap-1 border-t border-line py-2 first-of-type:border-t-0">
                    <div class="flex items-start justify-between gap-3 text-sm">
                        <span>{{ $part['label'] }} <span class="chip ml-1 bg-surface text-muted">bobot {{ $part['weight'] }}%</span></span>
                        <span class="shrink-0 font-extrabold">{{ $part['score'] ?? '—' }}</span>
                    </div>
                    <x-progress :percent="$part['score'] ?? 0" :tone="$part['score'] === null ? 'bg-slate-300' : ($part['score'] >= 80 ? 'bg-ok' : ($part['score'] >= 70 ? 'bg-gold' : 'bg-rose-500'))" />
                    <span class="text-[11px] text-muted">{{ $part['detail'] }}</span>
                </div>
            @endforeach
            <p class="mt-1 text-[11px] text-muted">Skor = rata-rata berbobot komponen yang memiliki data; komponen tanpa data tidak dihitung. Hijau ≥ 80, kuning 70–79, merah &lt; 70.</p>
        </section>

        <section class="card flex gap-3 border border-[#C9D3E3] bg-info-soft/60">
            <x-icon name="alert" class="mt-0.5 shrink-0 text-navy" />
            <div class="flex flex-col gap-0.5">
                <span class="text-xs font-bold tracking-wide text-navy uppercase">Penyebab</span>
                <p class="text-sm" data-test="value-cause">{{ $v['cause'] }}</p>
            </div>
        </section>

        <section class="card flex flex-col gap-1.5">
            <h2 class="card-title">Upaya perbaikan</h2>
            @foreach ($v['fixes'] as $fix)
                <div class="flex gap-2 text-sm"><x-icon name="check" size="16" class="mt-0.5 shrink-0 text-ok" /><span>{{ $fix }}</span></div>
            @endforeach
        </section>

        <div class="flex flex-col gap-2 lg:flex-row">
            <a href="{{ $v['action']['route'] }}" wire:navigate class="btn-primary">{{ $v['action']['label'] }}</a>
            <a href="{{ route('kadis.dashboard', $q) }}" wire:navigate class="btn-outline">Kembali ke dashboard</a>
        </div>
    </main>
</div>
