@php($isCurrent ??= true)
@php($unit ??= 'hari kerja')
{{-- Tab periode + navigasi tanggal (dipakai tab Harian, Mingguan, Bulanan) --}}
<div class="mt-3 flex flex-col gap-2.5">
    <div role="tablist" aria-label="Pilih periode" class="flex gap-1 rounded-xl bg-navy-2 p-1">
        @foreach (\App\Services\KadisPeriodService::PERIODS as $key => $label)
            <button type="button" role="tab" aria-selected="{{ $period === $key ? 'true' : 'false' }}" wire:click="$set('period', '{{ $key }}')"
                @class(['flex-1 rounded-lg px-3 py-1.5 text-xs font-bold', 'bg-white text-navy' => $period === $key, 'text-[#C9D3E3] hover:bg-white/10' => $period !== $key])>{{ $label }}</button>
        @endforeach
    </div>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <span class="text-xs text-[#C9D3E3]">Ketuk angka atau grafik untuk melihat sumber datanya</span>
        <div class="flex items-center gap-1 rounded-xl bg-navy-2 p-1 text-xs font-semibold">
            <button type="button" wire:click="shiftDay(-1)" class="rounded-lg px-2.5 py-1.5 hover:bg-white/10" aria-label="{{ ucfirst($unit) }} sebelumnya">‹</button>
            <input type="date" wire:model.live="date" max="{{ today()->toDateString() }}" class="w-[8.5rem] rounded-lg bg-transparent px-1 py-1 text-center text-white [color-scheme:dark]" aria-label="Pilih tanggal">
            <button type="button" wire:click="shiftDay(1)" @disabled($isCurrent) class="rounded-lg px-2.5 py-1.5 hover:bg-white/10 disabled:opacity-30" aria-label="{{ ucfirst($unit) }} berikutnya">›</button>
            @unless ($isCurrent)
                <button type="button" wire:click="$set('date', '{{ today()->toDateString() }}')" class="rounded-lg bg-white px-2.5 py-1.5 text-navy">Kini</button>
            @endunless
        </div>
    </div>
</div>
