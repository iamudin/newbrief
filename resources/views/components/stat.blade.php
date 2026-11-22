@props(['label', 'value', 'hint' => null, 'tone' => 'navy'])
@php
    $tones = ['navy' => 'text-navy', 'ok' => 'text-ok', 'warn' => 'text-amber-700', 'bad' => 'text-rose-600', 'gold' => 'text-[#7A5A00]'];
@endphp
<div {{ $attributes->merge(['class' => 'card flex flex-col gap-1 !p-3.5']) }}>
    <span class="text-[11px] font-semibold text-muted uppercase tracking-wide">{{ $label }}</span>
    <span class="text-2xl font-extrabold {{ $tones[$tone] ?? $tones['navy'] }}">{{ $value }}</span>
    @if ($hint)<span class="text-[11px] text-muted">{{ $hint }}</span>@endif
</div>
