<div wire:poll.30s>
    <x-page-header title="Notifikasi">
        <x-slot:actions><button type="button" wire:click="markAllRead" class="btn-sm btn border border-navy-3 bg-navy-2 text-white">Tandai semua dibaca</button></x-slot:actions>
    </x-page-header>
    <main class="mx-auto flex w-full max-w-3xl flex-col gap-2 px-4 py-4 lg:px-8">
        @forelse ($notifications as $n)
            @php $kind = $n->data['kind'] ?? 'info'; @endphp
            <button type="button" wire:click="open('{{ $n->id }}')" wire:key="n-{{ $n->id }}" @class(['card flex cursor-pointer items-start gap-3 !p-3 text-left', 'border-l-4 border-l-gold' => ! $n->read_at, 'opacity-70' => $n->read_at])>
                <span @class(['mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full', 'bg-rose-100 text-rose-600' => $kind === 'warning', 'bg-emerald-100 text-emerald-700' => in_array($kind, ['success', 'confirm']), 'bg-info-soft text-navy' => ! in_array($kind, ['warning', 'success', 'confirm'])])><x-icon :name="$kind === 'warning' ? 'alert' : 'bell'" size="15" /></span>
                <span class="flex-1"><span class="block text-sm font-semibold">{{ $n->data['title'] ?? '' }}</span><span class="block text-xs text-muted">{{ $n->data['body'] ?? '' }}</span><span class="block text-[10px] text-muted">{{ $n->created_at->diffForHumans() }}</span></span>
            </button>
        @empty
            <x-empty>Belum ada notifikasi.</x-empty>
        @endforelse
        {{ $notifications->links() }}
    </main>
</div>
