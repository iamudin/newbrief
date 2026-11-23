@php
    $user = auth()->user();
    $nav = \App\Support\Navigation::for($user);
    $position = $user->position();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0E2B4B">
    <link rel="icon" type="image/png" href="/images/logo-brief.png">
    <title>{{ $title ?? 'BRIEF' }} · BRIEF</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-surface font-sans text-ink antialiased selection:bg-gold selection:text-navy">
    <x-toast />
    <div class="lg:flex lg:min-h-dvh">
        {{-- Sidebar desktop --}}
        <aside class="hidden w-64 shrink-0 flex-col bg-navy text-white lg:sticky lg:top-0 lg:flex lg:h-dvh">
            <div class="flex items-center gap-2.5 px-5 py-5">
                <div class="flex size-9 items-center justify-center rounded-[10px] bg-white"><img src="/images/logo-brief.png" alt="" class="size-[30px] object-contain"></div>
                <div class="flex flex-col"><span class="text-lg leading-tight font-extrabold tracking-[2px]">BRIEF</span><span class="text-[10px] text-[#C9D3E3]">Instruksi Berjenjang</span></div>
            </div>
            <div class="mx-4 mb-4 rounded-xl bg-navy-2 p-3">
                <div class="text-sm font-bold">{{ $user->name }}</div>
                <div class="text-[11px] text-[#C9D3E3]">{{ $position?->name }}</div>
                <span class="chip mt-2 bg-gold text-navy">{{ $user->level()?->label() }}</span>
            </div>
            <nav class="flex flex-1 flex-col gap-1 px-3">
                @foreach ($nav as $item)
                    <a href="{{ route($item['route']) }}" wire:navigate @class(['flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold', 'bg-white text-navy' => request()->routeIs(...(array) $item['active']), 'text-[#C9D3E3] hover:bg-navy-2' => ! request()->routeIs(...(array) $item['active'])])>
                        <x-icon :name="$item['icon']" size="18" />{{ $item['label'] }}
                        @if (! empty($item['badge']))<span class="ml-auto chip bg-gold text-navy">{{ $item['badge'] }}</span>@endif
                    </a>
                @endforeach
                <a href="{{ route('notifications') }}" wire:navigate @class(['flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold', 'bg-white text-navy' => request()->routeIs('notifications'), 'text-[#C9D3E3] hover:bg-navy-2' => ! request()->routeIs('notifications')])>
                    <x-icon name="bell" size="18" />Notifikasi
                    @if ($count = $user->unreadNotifications()->count())<span class="ml-auto chip bg-gold text-navy">{{ $count }}</span>@endif
                </a>
            </nav>
            <a href="{{ route('password.change') }}" class="mx-3 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-[#C9D3E3] hover:bg-navy-2"><x-icon name="key" size="18" />Ganti password</a>
            <form method="POST" action="{{ route('logout') }}" class="p-4 pt-1">
                @csrf
                <button class="flex w-full cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-rose-300 hover:bg-navy-2"><x-icon name="logout" size="18" />Keluar</button>
            </form>
        </aside>

        <div class="flex min-h-dvh flex-1 flex-col pb-[calc(64px+env(safe-area-inset-bottom))] lg:pb-0">
            {{ $slot }}
        </div>
    </div>

    {{-- Bottom nav mobile --}}
    <nav aria-label="Menu utama" class="fixed inset-x-0 bottom-0 z-[900] grid border-t border-line bg-white pb-[env(safe-area-inset-bottom)] lg:hidden" style="grid-template-columns: repeat({{ count($nav) }}, minmax(0, 1fr));">
        @foreach ($nav as $item)
            <a href="{{ route($item['route']) }}" wire:navigate @class(['flex h-16 flex-col items-center justify-center gap-[3px] text-[10px]', 'font-bold text-navy' => request()->routeIs(...(array) $item['active']), 'font-semibold text-muted' => ! request()->routeIs(...(array) $item['active'])])>
                <span class="relative"><x-icon :name="$item['icon']" size="20" />@if (! empty($item['badge']))<span class="absolute -top-1.5 -right-2.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-600 px-1 text-[9px] font-extrabold text-white">{{ $item['badge'] > 9 ? '9+' : $item['badge'] }}</span>@endif</span>{{ $item['label'] }}
            </a>
        @endforeach
    </nav>
</body>
</html>
