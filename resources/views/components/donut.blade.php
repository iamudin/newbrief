@props(['pct' => null, 'target' => null, 'status' => 'none', 'size' => 84])
{{-- Donat capaian: busur = capaian, garis tipis = posisi target --}}
@php
    $color = \App\Services\KadisDashboardService::TONES[$status][0] ?? '#94A3B8';
    $r = 36;
    $c = 2 * M_PI * $r;
    $value = max(0, min(100, (int) ($pct ?? 0)));
    $angle = $target !== null ? (max(0, min(100, $target)) / 100) * 360 - 90 : null;
@endphp
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 90 90" role="img" aria-label="{{ $pct === null ? 'Belum ada data' : $pct.'% dari target '.$target.'%' }}" {{ $attributes }}>
    <circle cx="45" cy="45" r="{{ $r }}" fill="none" stroke="#E8EBF0" stroke-width="9" />
    @if ($pct !== null)
        <circle cx="45" cy="45" r="{{ $r }}" fill="none" stroke="{{ $color }}" stroke-width="9" stroke-linecap="round"
            stroke-dasharray="{{ round($c * $value / 100, 2) }} {{ round($c, 2) }}" transform="rotate(-90 45 45)" />
    @endif
    @if ($angle !== null)
        <line x1="{{ 45 + 29 * cos(deg2rad($angle)) }}" y1="{{ 45 + 29 * sin(deg2rad($angle)) }}" x2="{{ 45 + 43 * cos(deg2rad($angle)) }}" y2="{{ 45 + 43 * sin(deg2rad($angle)) }}" stroke="#1A1F2B" stroke-width="2" />
    @endif
    <text x="45" y="{{ $target !== null ? 46 : 50 }}" text-anchor="middle" font-size="17" font-weight="800" fill="{{ $pct === null ? '#94A3B8' : '#1A1F2B' }}">{{ $pct === null ? '—' : $pct.'%' }}</text>
    @if ($target !== null)
        <text x="45" y="60" text-anchor="middle" font-size="9" fill="#5B6474">/{{ $target }}%</text>
    @endif
</svg>
