<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/images/logo-brief.png">
    <title>{{ $title ?? 'Super Admin' }} · BRIEF Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-surface font-sans text-ink antialiased">
    <x-toast />
    <div class="flex min-h-dvh items-center justify-center p-6 text-center lg:hidden">
        <div class="card max-w-sm">
            <x-icon name="alert" size="28" class="mx-auto mb-2 text-gold" />
            <h1 class="font-bold">Panel Super Admin khusus desktop</h1>
            <p class="mt-1 text-sm text-muted">Buka halaman ini dari komputer dengan lebar layar minimal 1024px.</p>
        </div>
    </div>
    <div class="hidden min-h-dvh lg:flex">
        <aside class="sticky top-0 flex h-dvh w-64 shrink-0 flex-col bg-navy text-white">
            <div class="flex items-center gap-2.5 px-5 py-5">
                <div class="flex size-9 items-center justify-center rounded-[10px] bg-white"><img src="/images/logo-brief.png" alt="" class="size-[30px] object-contain"></div>
                <div class="flex flex-col"><span class="text-lg leading-tight font-extrabold tracking-[2px]">BRIEF</span><span class="text-[10px] text-gold">SUPER ADMIN</span></div>
            </div>
            <nav class="flex flex-1 flex-col gap-1 px-3">
                @foreach (\App\Support\Navigation::admin() as $item)
                    <a href="{{ route($item['route']) }}" wire:navigate @class(['flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold', 'bg-white text-navy' => request()->routeIs($item['route']), 'text-[#C9D3E3] hover:bg-navy-2' => ! request()->routeIs($item['route'])])>
                        <x-icon :name="$item['icon']" size="18" />{{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
            <div class="border-t border-navy-2 p-4 text-xs text-[#C9D3E3]">
                <div class="mb-2 font-semibold text-white">{{ auth()->user()->name }}</div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="flex cursor-pointer items-center gap-2 font-semibold text-rose-300"><x-icon name="logout" size="16" />Keluar</button></form>
            </div>
        </aside>
        <main class="flex-1 overflow-x-auto">
            <header class="flex items-center justify-between border-b border-line bg-white px-8 py-5">
                <div>
                    <h1 class="text-xl font-bold text-navy">{{ $title ?? 'Super Admin' }}</h1>
                    @isset($subtitle)<p class="text-sm text-muted">{{ $subtitle }}</p>@endisset
                </div>
                <span class="text-sm text-muted">{{ now()->translatedFormat('l, d F Y') }}</span>
            </header>
            <div class="p-8">{{ $slot }}</div>
        </main>
    </div>
</body>
</html>
