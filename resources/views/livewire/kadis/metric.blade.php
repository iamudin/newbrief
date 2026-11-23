@php
    use App\Services\KadisDashboardService as K;
    $q = $data['is_today'] ? [] : ['tanggal' => $data['date']->toDateString()];
@endphp
<div>
    <x-page-header :title="$detail['title']" :subtitle="'Sumber data · '.$data['date']->translatedFormat('l, d F Y')" :back="route('kadis.dashboard', $q)">
        <div class="mt-3 -mx-5 flex gap-1.5 overflow-x-auto px-5 pb-1 lg:mx-0 lg:flex-wrap lg:px-0">
            @foreach (K::METRICS as $key => $label)
                <a href="{{ route('kadis.metric', ['metric' => $key, ...$q]) }}" wire:navigate @class(['shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold', 'bg-white text-navy' => $key === $metric, 'bg-navy-2 text-[#C9D3E3]' => $key !== $metric])>{{ $label }}</a>
            @endforeach
        </div>
    </x-page-header>

    <main class="mx-auto flex w-full max-w-4xl flex-col gap-3 px-4 py-4 lg:px-8">
        <section class="card flex flex-col gap-1.5">
            <span class="text-2xl font-extrabold {{ K::tone($detail['status'], 2) }}" data-test="metric-value">{{ $detail['value'] }}</span>
            <span class="chip self-start {{ K::tone($detail['status']) }}">{{ $detail['target'] }}</span>
        </section>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <section class="card flex flex-col gap-1">
                <h2 class="text-xs font-bold tracking-wide text-muted uppercase">Cara menghitung</h2>
                <p class="text-sm">{{ $detail['calc'] }}</p>
            </section>
            <section class="card flex flex-col gap-1">
                <h2 class="text-xs font-bold tracking-wide text-muted uppercase">Sumber data</h2>
                <p class="text-sm">{{ $detail['source'] }}</p>
            </section>
        </div>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            @foreach ($detail['groups'] as $group)
                <section class="card flex flex-col gap-1">
                    <h2 class="card-title mb-1">{{ $group['title'] }}</h2>
                    @forelse ($group['rows'] as $row)
                        <div class="flex items-center justify-between gap-3 border-t border-line py-2 text-sm first-of-type:border-t-0">
                            <span class="flex min-w-0 items-center gap-2"><span class="size-2.5 shrink-0 rounded-full" style="background: {{ K::TONES[$row['status']][0] ?? '#94A3B8' }}"></span><span>{{ $row['label'] }}</span></span>
                            <span class="shrink-0 text-right text-xs font-bold">{{ $row['right'] }}</span>
                        </div>
                    @empty
                        <span class="text-xs text-muted">Belum ada data.</span>
                    @endforelse
                </section>
            @endforeach
        </div>

        <section class="card flex gap-3 border border-[#C9D3E3] bg-info-soft/60">
            <x-icon name="alert" class="mt-0.5 shrink-0 text-navy" />
            <div class="flex flex-col gap-0.5">
                <span class="text-xs font-bold tracking-wide text-navy uppercase">Kesimpulan</span>
                <p class="text-sm" data-test="metric-insight">{{ $detail['insight'] }}</p>
            </div>
        </section>

        <section class="card flex flex-col gap-1">
            <div class="mb-1 flex items-center justify-between gap-2">
                <h2 class="card-title">{{ $detail['people_title'] }}</h2>
                <span class="text-xs text-muted">{{ $detail['people']->count() }}</span>
            </div>
            @forelse ($detail['people'] as $p)
                @php $tag = isset($p['url']) ? 'a' : 'div'; @endphp
                <{{ $tag }} @if (isset($p['url'])) href="{{ $p['url'] }}" wire:navigate @endif class="flex items-start justify-between gap-3 border-t border-line py-2 first-of-type:border-t-0">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold">{{ $p['name'] }}</div>
                        <div class="text-[11px] text-muted">{{ $p['sub'] }}</div>
                        @if (! empty($p['issues']))
                            <ul class="mt-1 flex flex-col gap-0.5 text-[11px] text-rose-600">
                                @foreach ($p['issues'] as $issue)<li>• {{ $issue }}</li>@endforeach
                            </ul>
                        @endif
                    </div>
                    @if (! empty($p['issues']))
                        <span class="chip shrink-0 {{ $p['reminded_at'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $p['reminded_at'] ? 'Diingatkan '.$p['reminded_at'] : 'Belum diingatkan' }}</span>
                    @else
                        <span class="chip max-w-[45%] shrink-0 text-right {{ K::tone($p['status']) }}">{{ $p['right'] }}</span>
                    @endif
                </{{ $tag }}>
            @empty
                <x-empty>Tidak ada data.</x-empty>
            @endforelse
        </section>

        <div class="flex flex-col gap-2 lg:flex-row">
            @if ($metric === 'patuh' && $data['is_today'] && $detail['people']->isNotEmpty())
                <button type="button" wire:click="sendReminders" wire:confirm="Kirim pengingat (notifikasi + WhatsApp) ke {{ $toRemind }} pegawai tidak patuh?" @disabled(! $toRemind) wire:loading.attr="disabled" class="btn-danger" data-test="remind">
                    {{ $toRemind ? 'Kirim pengingat ke '.$toRemind.' pegawai' : 'Semua sudah diingatkan hari ini' }}
                </button>
            @endif
            @if ($detail['next'])
                <a href="{{ $detail['next']['route'] }}" wire:navigate class="btn-primary">{{ $detail['next']['label'] }}</a>
            @endif
            <a href="{{ route('kadis.dashboard', $q) }}" wire:navigate class="btn-outline">Kembali ke dashboard</a>
        </div>
    </main>
</div>
