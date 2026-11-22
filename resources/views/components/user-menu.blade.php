@php
    $user = auth()->user();
    $unread = $user->unreadNotifications()->count();
@endphp
<div class="flex items-center gap-2">
    <a href="{{ route('notifications') }}" wire:navigate aria-label="Notifikasi" class="relative flex size-8 items-center justify-center rounded-full border border-navy-3 bg-navy-2 text-white">
        <x-icon name="bell" size="15" />
        @if ($unread)
            <span class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-gold px-1 text-[9px] font-extrabold text-navy">{{ $unread > 9 ? '9+' : $unread }}</span>
        @endif
    </a>
    <span class="flex items-center gap-1.5 rounded-full border border-navy-3 bg-navy-2 py-1 pr-2.5 pl-1.5">
        <span class="flex size-[22px] items-center justify-center rounded-full bg-gold text-[10px] font-extrabold text-navy">{{ $user->initials() }}</span>
        <span class="text-[11px] font-bold whitespace-nowrap text-gold">{{ $user->level()?->shortLabel() }}</span>
    </span>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" title="Keluar" class="flex size-8 cursor-pointer items-center justify-center rounded-full border border-rose-600/45 bg-rose-600/20 text-rose-300"><x-icon name="logout" size="15" /></button>
    </form>
</div>
