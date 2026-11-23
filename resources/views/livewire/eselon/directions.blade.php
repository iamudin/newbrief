<div>
    <x-page-header :title="$isEselon3 ? 'Arahan ke Eselon 4' : 'Bagi & Verifikasi'" :subtitle="'Batas meneruskan instruksi pukul '.\App\Services\ComplianceService::deadline(auth()->user()->level())" />

    <main class="mx-auto grid w-full max-w-6xl gap-3 px-4 py-4 lg:grid-cols-5 lg:px-8">
        <div class="flex flex-col gap-3 lg:col-span-2">
            <section class="card flex flex-col gap-2">
                <h2 class="card-title">Instruksi masuk hari ini</h2>
                @forelse ($inbox as $r)
                    <div wire:key="in-{{ $r->id }}" class="rounded-xl border border-line p-3">
                        <div class="flex items-center justify-between gap-2">
                            <span @class(['chip', 'bg-gold-soft text-[#7A5A00]' => $r->direction->type === 'priority', 'bg-info-soft text-navy' => $r->direction->type !== 'priority'])>{{ $r->direction->typeLabel() }}</span>
                            <span class="text-[11px] text-muted">{{ $r->direction->fromPosition->name }} · {{ $r->direction->created_at->format('H.i') }}</span>
                        </div>
                        <p class="mt-1.5 text-sm">{{ str($r->direction->content)->limit(160) }}</p>
                        <div class="mt-2 flex gap-2">
                            @if (! $r->confirmed_at)
                                <button type="button" x-data="{ busy: false }" :disabled="busy" x-on:click="busy = true; await $wire.confirm({{ $r->id }}, await briefLocate()); busy = false" class="btn-gold btn-sm flex-1 tracking-widest">SIAP</button>
                            @else
                                <span class="chip bg-emerald-100 text-emerald-700">SIAP · {{ $r->confirmed_at->format('H.i') }}</span>
                            @endif
                            <a href="{{ route('recipient.show', $r) }}" wire:navigate class="btn-outline btn-sm">{{ $r->tasks->isNotEmpty() || $r->confirmed_at ? 'Kerjakan / lapor' : 'Detail' }}</a>
                        </div>
                        <x-recipient-attachments :recipient="$r" />
                    </div>
                @empty
                    <x-empty>Belum ada instruksi dari atasan hari ini. Anda tetap dapat membuat instruksi sendiri dan terhitung patuh.</x-empty>
                @endforelse
            </section>

            <section class="card flex flex-col gap-2">
                <h2 class="card-title">Perlu verifikasi <span class="chip bg-amber-100 text-amber-800">{{ $toVerify->count() }}</span></h2>
                @forelse ($toVerify as $r)
                    <div role="link" tabindex="0" x-data x-on:click="Livewire.navigate('{{ route('recipient.show', $r) }}')" class="cursor-pointer rounded-xl border border-gold/50 bg-gold-soft/40 p-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <div><div class="text-sm font-semibold">{{ $r->toUser?->name }}</div><div class="text-[11px] text-muted">{{ $r->toPosition->name }} · lapor {{ $r->reported_at->format('d/m H.i') }} · {{ $r->doneCount() }}/{{ $r->tasks->count() }} selesai</div></div>
                            <x-icon name="chevron" size="16" />
                        </div>
                        <x-recipient-attachments :recipient="$r" />
                    </div>
                @empty
                    <x-empty>Tidak ada laporan yang menunggu verifikasi.</x-empty>
                @endforelse
            </section>

            <section class="card flex flex-col gap-2">
                <h2 class="card-title">Terkirim hari ini</h2>
                @forelse ($sentToday as $r)
                    <div role="link" tabindex="0" x-data x-on:click="Livewire.navigate('{{ route('recipient.show', $r) }}')" class="cursor-pointer rounded-xl border border-line p-2.5 hover:bg-surface">
                        <div class="flex items-center justify-between gap-2">
                            <div class="min-w-0"><div class="truncate text-sm font-semibold">{{ $r->toUser?->name ?? $r->toPosition->name }}</div><div class="text-[11px] text-muted">{{ $r->direction->created_at->format('H.i') }} · {{ $r->doneCount() }}/{{ $r->tasks->count() }} tugas</div><x-wa-status :message="$r->whatsappFor()" class="mt-1" /></div>
                            <span class="chip {{ $r->stateClass() }}">{{ $r->stateLabel() }}</span>
                        </div>
                        <x-recipient-attachments :recipient="$r" />
                    </div>
                @empty
                    <x-empty>Belum ada instruksi terkirim hari ini.</x-empty>
                @endforelse
            </section>
        </div>

        <form x-data="{ busy: false }" x-on:submit.prevent="busy = true; await $wire.send(await briefLocate()); busy = false" class="card flex flex-col gap-3 self-start lg:col-span-3">
            <h2 class="card-title">{{ $isEselon3 ? 'Buat arahan untuk Eselon 4' : 'Teruskan tugas ke Staff' }}</h2>

            <div>
                <label class="label">Pemicu instruksi</label>
                <select wire:model="parentId" class="field">
                    <option value="">Tanpa pemicu (inisiatif sendiri)</option>
                    @foreach ($inbox as $r)<option value="{{ $r->direction_id }}">{{ $r->direction->typeLabel() }} · {{ str($r->direction->content)->limit(60) }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label">Isi instruksi</label>
                <textarea wire:model="content" rows="3" class="field" placeholder="Tulis arahan / instruksi…"></textarea>
                @error('content')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label">Item checklist untuk semua penerima <span class="font-normal text-muted">(satu per baris, opsional)</span></label>
                <textarea wire:model="common" rows="2" class="field" placeholder="Contoh: Laporkan kendala layanan hari ini"></textarea>
            </div>
            <div>
                <label class="label">Lampiran (opsional)</label>
                <input type="file" wire:model="attachment" class="text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-surface file:px-3 file:py-1.5 file:text-xs file:font-semibold">
                @error('attachment')<p class="error">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center justify-between">
                <span class="label !mb-0">Penerima & checklist per bawahan</span>
                <button type="button" wire:click="selectAll" class="text-xs font-bold text-navy">Pilih semua</button>
            </div>
            @error('recipients')<p class="error">{{ $message }}</p>@enderror
            @forelse ($children as $child)
                <div wire:key="child-{{ $child->id }}" @class(['rounded-xl border p-3', 'border-navy bg-info-soft/30' => $selected[$child->id] ?? false, 'border-line' => ! ($selected[$child->id] ?? false)])>
                    <label class="flex cursor-pointer items-center gap-2.5">
                        <input type="checkbox" wire:model.live="selected.{{ $child->id }}" class="size-4 accent-navy">
                        <span class="text-sm font-semibold">{{ $child->holder()?->name ?? 'Jabatan kosong' }}</span>
                        <span class="text-[11px] text-muted">{{ $child->name }}</span>
                    </label>
                    @if ($selected[$child->id] ?? false)
                        <div class="mt-2.5 flex flex-col gap-1.5 pl-6">
                            @if ($child->duties->isNotEmpty())<span class="text-[11px] font-bold text-ok-dark">Dari tupoksi</span>@endif
                            @foreach ($child->duties as $duty)
                                <label class="flex items-start gap-2 text-xs"><input type="checkbox" wire:model="duties.{{ $child->id }}.{{ $duty->id }}" class="mt-0.5 accent-ok">{{ $duty->title }}</label>
                            @endforeach
                            @if ($approvedInitiatives->has($child->id))
                                <span class="mt-1 text-[11px] font-bold text-[#7A5A00]">Inisiatif disetujui</span>
                                @foreach ($approvedInitiatives[$child->id] as $ini)
                                    <label class="flex items-start gap-2 text-xs"><input type="checkbox" wire:model="initiatives.{{ $child->id }}.{{ $ini->id }}" class="mt-0.5 accent-gold">{{ $ini->title }}</label>
                                @endforeach
                            @endif
                            <textarea wire:model="manual.{{ $child->id }}" rows="2" class="field mt-1 !py-2 text-xs" placeholder="Item tambahan khusus (satu per baris)"></textarea>
                        </div>
                    @endif
                </div>
            @empty
                <x-empty>Anda belum memiliki bawahan langsung di struktur. Hubungi super admin.</x-empty>
            @endforelse

            <button type="submit" :disabled="busy" class="btn-gold h-12 text-base" @disabled($children->isEmpty())>
                <span x-show="!busy">Kirim instruksi</span><span x-show="busy" x-cloak>Mengirim…</span>
            </button>
        </form>
    </main>
</div>
