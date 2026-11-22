@props(['checkin', 'compliance', 'deadline'])
<section class="card flex flex-col gap-2.5">
    @if ($checkin)
        <div class="flex items-center gap-3">
            <div class="flex size-10 items-center justify-center rounded-xl bg-ok-soft text-ok-dark"><x-icon name="ok" size="22" stroke-width="2.6" /></div>
            <div class="flex flex-col">
                <span class="text-[15px] font-bold text-ok-dark">Sudah SIAP · {{ $checkin->checked_in_at->format('H.i') }}</span>
                <span class="text-xs text-muted">Terekam: {{ \App\Models\LocationLog::zoneLabel($checkin->location_zone) }}</span>
            </div>
        </div>
    @else
        <div class="flex items-center gap-2 text-[13px] font-semibold text-ok-dark"><x-icon name="pin" size="18" />Lokasi direkam sekali saat menekan SIAP</div>
        <button type="button" x-data="{ busy: false }" :disabled="busy" x-on:click="busy = true; await $wire.checkIn(await briefLocate()); busy = false" class="btn-gold h-[54px] text-lg font-extrabold tracking-[3px]">
            <span x-show="!busy">SIAP</span><span x-show="busy" x-cloak class="text-sm tracking-normal">Merekam lokasi…</span>
        </button>
    @endif
    <div class="flex items-center justify-between text-[11px] text-muted">
        <span>Batas kepatuhan pukul {{ $deadline }}</span>
        @if ($compliance)<span class="chip {{ $compliance['status']->badgeClass() }}">{{ $compliance['status']->label() }}{{ $compliance['acted_at'] ? ' · '.$compliance['acted_at']->format('H.i') : '' }}</span>@endif
    </div>
</section>
