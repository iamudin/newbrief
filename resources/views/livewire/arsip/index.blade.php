<div>
    <x-page-header title="E-Arsip Surat Masuk" subtitle="Disposisi surat masuk berjenjang">
        @if ($isOperator)
            <x-slot:actions><a href="{{ route('arsip.create') }}" wire:navigate class="btn-gold btn-sm"><x-icon name="plus" size="14" />Input surat</a></x-slot:actions>
        @endif
        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
            <div class="flex flex-1 gap-1 rounded-xl bg-navy-2 p-1">
                <button type="button" wire:click="$set('tab', 'todo')" @class(['tab', 'tab-active' => $tab !== 'done', '!text-[#C9D3E3]' => $tab === 'done'])>{{ $isOperator ? 'Dalam proses' : 'Perlu tindakan' }} @if ($todoCount)<span class="ml-1 rounded-full bg-rose-500 px-1.5 text-[10px] text-white">{{ $todoCount }}</span>@endif</button>
                <button type="button" wire:click="$set('tab', 'done')" @class(['tab', 'tab-active' => $tab === 'done', '!text-[#C9D3E3]' => $tab !== 'done'])>{{ $isOperator ? 'Selesai' : 'Riwayat' }}</button>
            </div>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari pengirim / nomor / perihal…" class="field !border-0 sm:max-w-xs">
        </div>
    </x-page-header>

    <main class="mx-auto flex w-full max-w-5xl flex-col gap-2 px-4 py-4 lg:px-8">
        @forelse ($letters as $letter)
            @php $mine = $myDispositions->get($letter->id); @endphp
            <div wire:key="l-{{ $letter->id }}" role="link" tabindex="0" x-data x-on:click="Livewire.navigate('{{ route('arsip.show', $letter) }}')" class="card cursor-pointer !p-3 hover:border-navy-3">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="font-mono text-[11px] font-bold text-navy">{{ $letter->agenda_number }}</span>
                            <span class="chip {{ $letter->natureClass() }}">{{ $letter->natureLabel() }}</span>
                            <span class="text-[11px] text-muted">Diterima {{ $letter->received_date->translatedFormat('d M Y') }}</span>
                        </div>
                        <div class="mt-1 truncate text-sm font-bold">{{ $letter->sender }}</div>
                        <p class="text-[13px] text-ink">{{ str($letter->subject)->limit(120) }}</p>
                        <div class="text-[11px] text-muted">No. {{ $letter->letter_number }} · {{ $letter->letter_date->translatedFormat('d M Y') }}</div>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <span @class(['chip', 'bg-rose-100 text-rose-700' => $tab !== 'done' && ! $isOperator, 'bg-surface text-muted' => $tab === 'done' || $isOperator])>{{ $context[$letter->id] ?? '' }}</span>
                        @if ($mine)<span class="chip {{ $mine->stateClass() }}">{{ $mine->stateLabel() }}</span>@else<span class="chip {{ $letter->stageClass() }}">{{ $letter->stageLabel() }}</span>@endif
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-1 border-t border-line pt-2">
                    <x-wa-status :message="$letter->whatsappFor('arsip_paraf')" :label="'Paraf '.($letter->verifierPosition?->name ?? '')" />
                    <x-wa-status :message="$letter->whatsappFor('arsip_kadis')" label="Kadis" />
                </div>
                @if ($letter->disposed_at && $letter->dispositions->isNotEmpty())
                    <div class="mt-1.5 flex flex-wrap items-center gap-1">
                        <span class="text-[10px] text-muted">Disposisi:</span>
                        @foreach ($letter->dispositions->whereNull('parent_id') as $d)
                            <span class="inline-flex items-center gap-0.5"><span class="chip {{ $d->stateClass() }}">{{ $d->toPosition->name }}</span><x-wa-status :message="$d->whatsappFor()" compact /></span>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <x-empty>{{ $tab === 'done' ? 'Belum ada riwayat surat.' : 'Tidak ada surat yang menunggu tindakan Anda.' }}</x-empty>
        @endforelse
        {{ $letters->links(data: ['scrollTo' => false]) }}
    </main>
</div>
