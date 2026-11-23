<div>
    <x-page-header :title="$letter ? 'Ubah Surat Masuk' : 'Input Surat Masuk'" subtitle="E-Arsip · Disposisi surat masuk" :back="$letter ? route('arsip.show', $letter) : route('arsip.index')" />

    <main class="mx-auto w-full max-w-3xl px-4 py-4 lg:px-8">
        <form wire:submit="save" class="card grid gap-3 sm:grid-cols-2">
            <div class="flex items-center justify-between rounded-xl bg-surface p-3 sm:col-span-2">
                <div><div class="text-[11px] font-semibold text-muted uppercase">No. Agenda (otomatis)</div><div class="font-mono text-lg font-bold text-navy">{{ $agenda }}</div></div>
                <div class="text-right text-[11px] text-muted">Diparaf oleh<br><b class="text-ink">{{ $verifier?->name ?? '—' }}</b> · {{ $verifier?->holder()?->name ?? 'kosong' }}</div>
            </div>

            <div class="sm:col-span-2"><label class="label">Surat dari</label><input wire:model="sender" class="field" placeholder="Instansi / pengirim">@error('sender')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Nomor surat</label><input wire:model="letter_number" class="field">@error('letter_number')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Tanggal surat</label><input type="date" wire:model="letter_date" class="field">@error('letter_date')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Tanggal diterima</label><input type="date" wire:model="received_date" max="{{ today()->toDateString() }}" class="field">@error('received_date')<p class="error">{{ $message }}</p>@enderror</div>
            <div>
                <label class="label">Sifat</label>
                <div class="grid grid-cols-2 gap-1.5">
                    @foreach (\App\Models\Letter::NATURES as $key => $label)
                        <label @class(['flex cursor-pointer items-center gap-2 rounded-lg border px-2.5 py-2 text-xs font-semibold', 'border-navy bg-info-soft/50 text-navy' => $nature === $key, 'border-line' => $nature !== $key])>
                            <input type="radio" wire:model.live="nature" value="{{ $key }}" class="accent-navy">{{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('nature')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2"><label class="label">Perihal</label><textarea wire:model="subject" rows="3" class="field"></textarea>@error('subject')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="sm:col-span-2">
                <label class="label">File surat (PDF/JPG/PNG, maks 10 MB){{ $letter ? ' — kosongkan bila tidak diganti' : '' }}</label>
                <input type="file" wire:model="file" accept=".pdf,.jpg,.jpeg,.png" class="text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-surface file:px-3 file:py-1.5 file:text-xs file:font-semibold">
                <span wire:loading wire:target="file" class="block text-[11px] font-semibold text-navy">Mengunggah…</span>
                @if ($file && ! $errors->has('file'))<span class="block text-[11px] font-semibold text-ok-dark">Siap: {{ $file->getClientOriginalName() }}</span>@endif
                @if ($letter)<span class="block text-[11px] text-muted">File saat ini: {{ $letter->file_name }}</span>@endif
                @error('file')<p class="error">{{ $message }}</p>@enderror
            </div>
            <button class="btn-gold h-12 text-base sm:col-span-2" wire:loading.attr="disabled" wire:target="save,file">{{ $letter ? 'Simpan perubahan' : 'Simpan & teruskan untuk paraf' }}</button>
        </form>
    </main>
</div>
