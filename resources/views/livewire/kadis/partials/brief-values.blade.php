{{-- Nilai B-R-I-E-F (0–100) untuk satu periode; $values, $title, $valueQuery --}}
@php($tones = \App\Services\KadisDashboardService::TONES)
<section class="card flex flex-col gap-3" data-test="brief-values">
    <div class="flex items-baseline justify-between gap-2">
        <h2 class="card-title">{{ $title }}</h2>
        <span class="text-[11px] text-muted">skor 0–100</span>
    </div>
    <div class="grid grid-cols-5 gap-1.5">
        @foreach ($values as $v)
            <a href="{{ route('kadis.value', ['value' => $v['key'], ...$valueQuery]) }}" wire:navigate class="flex flex-col items-center gap-0.5 rounded-xl border-2 py-2.5 hover:opacity-90" style="border-color: {{ $tones[$v['status']][0] }}20; background: {{ $tones[$v['status']][0] }}12" data-value="{{ $v['key'] }}">
                <span class="text-lg font-extrabold" style="color: {{ $tones[$v['status']][0] }}">{{ $v['key'] }}</span>
                <span class="text-base font-extrabold text-ink">{{ $v['score'] ?? '—' }}</span>
                <span class="text-[10px] font-semibold text-muted">{{ $v['name'] }}</span>
            </a>
        @endforeach
    </div>
    @php($lowest = collect($values)->whereNotNull('score')->sortBy('score')->first())
    <a href="{{ route('kadis.value', ['value' => $lowest['key'] ?? 'B', ...$valueQuery]) }}" wire:navigate class="rounded-xl bg-surface p-3 text-xs hover:bg-line/60">
        <b>Kesimpulan:</b> {{ \App\Services\KadisPeriodService::conclusion($values) }}
    </a>
</section>
