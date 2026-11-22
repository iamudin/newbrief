@props(['show', 'title', 'close', 'width' => 'max-w-lg'])
@if ($show)
    <div class="fixed inset-0 z-[950] flex items-center justify-center bg-black/40 p-6" wire:keydown.escape.window="{{ $close }}">
        <div class="card w-full {{ $width }} max-h-[90dvh] overflow-y-auto !p-6">
            <div class="mb-4 flex items-center justify-between"><h2 class="text-base font-bold text-navy">{{ $title }}</h2><button type="button" wire:click="{{ $close }}" class="cursor-pointer text-muted"><x-icon name="x" size="18" /></button></div>
            {{ $slot }}
        </div>
    </div>
@endif
