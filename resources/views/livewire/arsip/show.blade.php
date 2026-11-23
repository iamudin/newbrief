<div>
    <x-page-header :title="$letter->sender" :subtitle="'No. Agenda '.$letter->agenda_number" :back="route('arsip.index')">
        <div class="mt-3 flex flex-wrap gap-2">
            <span class="chip {{ $letter->natureClass() }}">{{ $letter->natureLabel() }}</span>
            <span class="chip {{ $letter->stageClass() }}">{{ $letter->stageLabel() }}</span>
            @if ($mine)<span class="chip {{ $mine->stateClass() }}">Disposisi Anda: {{ $mine->stateLabel() }}</span>@endif
        </div>
    </x-page-header>

    <main class="mx-auto grid w-full max-w-6xl gap-3 px-4 py-4 lg:grid-cols-5 lg:px-8">
        <div class="flex flex-col gap-3 lg:col-span-3">
            {{-- Data surat --}}
            <section class="card flex flex-col gap-2">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="card-title">Data surat</h2>
                    <div class="flex gap-1.5">
                        @if ($letter->disposed_at)<a href="{{ route('arsip.print', $letter) }}" target="_blank" class="btn-outline btn-sm"><x-icon name="print" size="13" />Lembar disposisi</a>@endif
                        @if ($canEdit)<a href="{{ route('arsip.edit', $letter) }}" wire:navigate class="btn-outline btn-sm"><x-icon name="edit" size="13" />Ubah</a>@endif
                    </div>
                </div>
                <dl class="grid grid-cols-[110px_1fr] gap-x-3 gap-y-1 text-sm">
                    <dt class="text-muted">Surat dari</dt><dd class="font-semibold">{{ $letter->sender }}</dd>
                    <dt class="text-muted">Nomor</dt><dd>{{ $letter->letter_number }}</dd>
                    <dt class="text-muted">Tgl. surat</dt><dd>{{ $letter->letter_date->translatedFormat('d F Y') }}</dd>
                    <dt class="text-muted">Diterima</dt><dd>{{ $letter->received_date->translatedFormat('d F Y') }}</dd>
                    <dt class="text-muted">Perihal</dt><dd class="whitespace-pre-line">{{ $letter->subject }}</dd>
                    <dt class="text-muted">Diinput</dt><dd class="text-xs">{{ $letter->creator->name }} · {{ $letter->created_at->format('d/m/Y H.i') }}</dd>
                </dl>
                <div class="mt-1 overflow-hidden rounded-xl border border-line bg-surface">
                    @if ($letter->isPdf())
                        <iframe src="{{ route('file', ['surat', $letter->id]) }}" class="h-[38dvh] w-full lg:h-[70dvh]" title="Pratinjau surat"></iframe>
                    @else
                        <img src="{{ route('file', ['surat', $letter->id]) }}" alt="Pindaian surat" class="mx-auto max-h-[70dvh] object-contain">
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('file', ['surat', $letter->id]) }}" target="_blank" class="btn-outline btn-sm">Buka layar penuh</a>
                    <a href="{{ route('file', ['surat', $letter->id]) }}?unduh=1" class="btn-outline btn-sm"><x-icon name="clip" size="13" />Unduh {{ str($letter->file_name)->limit(24) }}</a>
                </div>
                @if ($canEdit)
                    <button type="button" wire:click="delete" wire:confirm="Hapus surat {{ $letter->agenda_number }}? File juga ikut terhapus." class="self-start text-xs font-semibold text-rose-600 underline">Hapus surat</button>
                @endif
            </section>
        </div>

        <div class="flex flex-col gap-3 lg:col-span-2">
            {{-- Alur --}}
            <section class="card flex flex-col gap-2">
                <h2 class="card-title">Alur surat</h2>
                <ol class="flex flex-col gap-2 text-xs">
                    <li class="flex gap-2"><span class="mt-0.5 size-2.5 shrink-0 rounded-full bg-ok"></span><span><b>Diinput</b> {{ $letter->operatorPosition->name }} · {{ $letter->created_at->format('d/m H.i') }}</span></li>
                    <li class="flex gap-2"><span @class(['mt-0.5 size-2.5 shrink-0 rounded-full', 'bg-ok' => $letter->paraf_at, 'bg-amber-400' => ! $letter->paraf_at])></span>
                        <span><b>Paraf</b> {{ $letter->verifierPosition?->name ?? '—' }} · {{ $letter->paraf_at ? $letter->parafUser?->name.' · '.$letter->paraf_at->format('d/m H.i') : 'menunggu' }}@if ($letter->paraf_note)<span class="block text-muted">“{{ $letter->paraf_note }}”</span>@endif
                        <span class="mt-0.5 block"><x-wa-status :message="$letter->whatsappFor('arsip_paraf')" label="Notifikasi ke pemaraf" /></span></span></li>
                    <li class="flex gap-2"><span @class(['mt-0.5 size-2.5 shrink-0 rounded-full', 'bg-ok' => $letter->disposed_at, 'bg-amber-400' => $letter->paraf_at && ! $letter->disposed_at, 'bg-line' => ! $letter->paraf_at])></span>
                        <span><b>Disposisi Kepala Dinas</b> · {{ $letter->disposed_at ? $letter->disposer?->name.' · '.$letter->disposed_at->format('d/m H.i') : 'menunggu' }}
                        <span class="mt-0.5 block"><x-wa-status :message="$letter->whatsappFor('arsip_kadis')" label="Notifikasi ke Kadis" /></span></span></li>
                </ol>
                @if ($letter->disposed_at)
                    <div class="rounded-xl bg-navy p-3 text-white">
                        <div class="text-[11px] font-bold tracking-wider text-gold uppercase">Dengan harapan</div>
                        <ul class="mt-1 list-inside list-disc text-sm">
                            @forelse ($letter->expectations ?? [] as $e)<li>{{ $e }}</li>@empty<li>—</li>@endforelse
                        </ul>
                        @if ($letter->disposition_note)<p class="mt-2 text-sm text-[#C9D3E3]"><b class="text-white">Catatan:</b> {{ $letter->disposition_note }}</p>@endif
                    </div>
                    @include('livewire.arsip.partials.tree', ['parentId' => 0, 'depth' => 0])
                    @if ($canReset)
                        <button type="button" wire:click="resetDisposition" wire:confirm="Batalkan disposisi ini dan disposisi ulang?" class="btn-outline btn-sm self-start">Perbarui disposisi</button>
                        @error('reset')<p class="error">{{ $message }}</p>@enderror
                    @endif
                @endif
            </section>

            {{-- Paraf (Kasubag Umum / atasan operator) --}}
            @if ($isVerifier && ! $letter->paraf_at)
                <section class="card flex flex-col gap-2 border-gold">
                    <h2 class="card-title">Paraf & teruskan ke Kepala Dinas</h2>
                    <textarea wire:model="parafNote" rows="2" class="field" placeholder="Catatan untuk Kepala Dinas (opsional)"></textarea>
                    @error('paraf')<p class="error">{{ $message }}</p>@enderror
                    <button type="button" wire:click="paraf" class="btn-gold">Paraf & teruskan</button>
                </section>
            @endif

            {{-- Disposisi Kepala Dinas --}}
            @if ($isKadis && $letter->paraf_at && ! $letter->disposed_at)
                <form wire:submit="dispose" class="card flex flex-col gap-3 border-gold">
                    <h2 class="card-title">Disposisi kepada</h2>
                    <div class="flex flex-col gap-1.5">
                        @foreach ($kadisTargets as $p)
                            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-line px-3 py-2 text-sm">
                                <input type="checkbox" wire:model="dispositionTargets.{{ $p->id }}" class="size-4 accent-navy">
                                <span class="font-semibold">{{ $p->name }}</span><span class="text-[11px] text-muted">{{ $p->holder()?->name ?? 'kosong' }}</span>
                            </label>
                        @endforeach
                        @error('dispositionTargets')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <h3 class="card-title">Dengan harapan</h3>
                    @php $idx = 0; @endphp
                    @foreach (\App\Models\Letter::EXPECTATIONS as $group => $items)
                        <div>
                            <div class="mb-1 text-[11px] font-bold text-muted uppercase">{{ $group }}</div>
                            <div class="flex flex-col gap-1">
                                @foreach ($items as $item)
                                    <label class="flex items-start gap-2 text-sm" wire:key="exp-{{ $idx }}">
                                        <input type="checkbox" wire:model.live="expectations.{{ $idx }}" class="mt-0.5 accent-navy">
                                        <span class="flex-1">{{ $item['text'] }}{{ isset($item['prefix']) ? ' …' : '' }}
                                            @if (isset($item['prefix']) && ($expectations[$idx] ?? false))
                                                <input type="text" wire:model="expectationCustom.{{ $idx }}" class="field mt-1 !py-1.5 text-xs" placeholder="Nama unit / jabatan">
                                            @endif
                                        </span>
                                    </label>
                                    @php $idx++; @endphp
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    <div><label class="label">Catatan</label><textarea wire:model="dispositionNote" rows="3" class="field"></textarea></div>
                    <button type="submit" class="btn-gold h-12">Kirim disposisi</button>
                </form>
            @endif

            {{-- Tindak lanjut penerima disposisi --}}
            @if ($mine)
                <section class="card flex flex-col gap-3 border-gold">
                    <div>
                        <h2 class="card-title">Disposisi untuk Anda</h2>
                        <p class="text-[11px] text-muted">Dari {{ $mine->fromPosition->name }} ({{ $mine->fromUser->name }}) · {{ $mine->created_at->format('d/m H.i') }}</p>
                    </div>
                    @if ($mine->parent_id)
                        @if ($mine->note)<p class="rounded-lg bg-surface p-2.5 text-sm"><b>Catatan:</b> {{ $mine->note }}</p>@endif
                    @endif

                    <form wire:submit="sendReply" class="flex flex-col gap-2">
                        <label class="label !mb-0">Balasan / laporan tindak lanjut</label>
                        <textarea wire:model="reply" rows="2" class="field" placeholder="Tulis balasan ke pemberi disposisi"></textarea>
                        @error('reply')<p class="error">{{ $message }}</p>@enderror
                        <button class="btn-primary">{{ $mine->replied_at ? 'Perbarui balasan' : 'Kirim balasan' }}</button>
                    </form>

                    @if ($forwardOptions->isNotEmpty() && ! $mine->completed_at)
                        <form wire:submit="forward" class="flex flex-col gap-2 border-t border-line pt-3">
                            <label class="label !mb-0">Teruskan ke bawahan</label>
                            @foreach ($forwardOptions as $p)
                                @php $sent = in_array($p->id, $alreadyForwarded, true); @endphp
                                <label @class(['flex items-center gap-2.5 rounded-lg border border-line px-3 py-2 text-sm', 'opacity-50' => $sent, 'cursor-pointer' => ! $sent])>
                                    <input type="checkbox" wire:model="forwardTargets.{{ $p->id }}" class="size-4 accent-navy" @disabled($sent)>
                                    <span class="font-semibold">{{ $p->holder()?->name ?? 'Jabatan kosong' }}</span><span class="text-[11px] text-muted">{{ $p->name }}{{ $sent ? ' · sudah diteruskan' : '' }}</span>
                                </label>
                            @endforeach
                            @error('forwardTargets')<p class="error">{{ $message }}</p>@enderror
                            <textarea wire:model="forwardNote" rows="2" class="field" placeholder="Catatan / arahan untuk bawahan"></textarea>
                            <button class="btn-outline">Teruskan disposisi</button>
                        </form>
                    @endif

                    <div class="border-t border-line pt-3">
                        @if ($mine->completed_at)
                            <div class="flex items-center justify-between gap-2"><span class="chip bg-emerald-100 text-emerald-700">Selesai · {{ $mine->completed_at->format('d/m H.i') }}</span><button type="button" wire:click="complete(false)" class="text-xs font-semibold text-muted underline">Buka kembali</button></div>
                        @else
                            <button type="button" wire:click="complete(true)" class="btn-ok w-full">Tandai selesai / arsipkan</button>
                        @endif
                    </div>
                </section>
            @endif
        </div>
    </main>
</div>
