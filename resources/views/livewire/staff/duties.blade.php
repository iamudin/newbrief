<div>
    <x-page-header title="Tupoksi Saya" :subtitle="$position->name" />
    <main class="mx-auto grid w-full max-w-5xl gap-3 px-4 py-4 lg:grid-cols-2 lg:px-8">
        <div class="flex flex-col gap-3">
            <section class="card flex flex-col gap-1">
                <span class="text-base font-bold text-navy">{{ $position->name }}</span>
                <span class="text-[13px] text-muted">{{ $position->unit ?? '—' }} · Atasan: {{ $position->parent?->name }} ({{ $position->parent?->holder()?->name ?? 'kosong' }})</span>
            </section>
            <section class="card flex flex-col gap-2">
                <h2 class="card-title">Tugas pokok & fungsi</h2>
                @forelse ($position->duties as $i => $duty)
                    <div class="flex gap-3 rounded-xl bg-ok-soft/50 p-3">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-ok text-[11px] font-bold text-white">{{ $i + 1 }}</span>
                        <div><div class="text-sm font-semibold">{{ $duty->title }}</div>@if ($duty->description)<div class="text-xs text-muted">{{ $duty->description }}</div>@endif</div>
                    </div>
                @empty
                    <x-empty>Tupoksi jabatan belum diisi super admin.</x-empty>
                @endforelse
            </section>
        </div>
        <x-initiative-card :initiatives="$initiatives" />
    </main>
</div>
