@props(['title', 'subtitle' => null, 'back' => null])
{{-- Bar atas: fixed (sticky) di mobile, selalu terlihat saat halaman di-scroll --}}
<div x-data="{ scrolled: window.scrollY > 72 }" x-on:scroll.window.passive="scrolled = window.scrollY > 72"
     :class="scrolled ? 'shadow-lg shadow-navy/25' : ''"
     class="sticky top-0 z-[800] flex items-center justify-between gap-2 bg-navy px-5 pt-[max(12px,env(safe-area-inset-top))] pb-3 text-white transition-shadow lg:hidden"
     data-mobile-topbar>
    <div class="flex min-w-0 items-center gap-2.5">
        @if ($back)
            <a href="{{ $back }}" wire:navigate aria-label="Kembali" class="-ml-2.5 flex size-10 shrink-0 items-center justify-center text-white"><x-icon name="back" size="22" /></a>
        @else
            <div class="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-white"><img src="/images/logo-brief.png" alt="" class="size-[30px] object-contain"></div>
        @endif
        <span x-show="!scrolled" class="text-lg font-extrabold tracking-[2px] {{ $back ? 'hidden' : '' }}">BRIEF</span>
        <span x-show="scrolled" x-cloak class="truncate text-[15px] font-bold">{{ $title }}</span>
    </div>
    <x-user-menu />
</div>

<header class="bg-navy px-5 pt-1 pb-4 text-white lg:px-8 lg:pt-7 lg:pb-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="flex flex-col gap-0.5">
            <span class="text-xs text-[#C9D3E3]">{{ $subtitle ?? now()->translatedFormat('l, d F Y') }}</span>
            <h1 class="text-[22px] font-bold lg:text-2xl">{{ $title }}</h1>
        </div>
        @isset($actions)<div class="flex items-center gap-2">{{ $actions }}</div>@endisset
    </div>
    {{ $slot }}
</header>
