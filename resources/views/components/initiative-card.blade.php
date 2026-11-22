@props(['initiatives'])
<section class="card flex flex-col gap-2">
    <div>
        <h2 class="card-title">Tugas inisiatif</h2>
        <span class="text-xs text-muted">Ajukan ide kerja; setelah disetujui atasan, akan muncul sebagai pilihan checklist saat atasan memberi instruksi.</span>
    </div>
    <form wire:submit="proposeInitiative" class="flex flex-col gap-2">
        <input type="text" wire:model="initiativeTitle" class="field" placeholder="Uraian tugas inisiatif">
        @error('initiativeTitle')<p class="error">{{ $message }}</p>@enderror
        <textarea wire:model="initiativeDescription" rows="2" class="field" placeholder="Keterangan (opsional)"></textarea>
        <button class="btn-primary">Ajukan ke atasan</button>
    </form>
    @foreach ($initiatives as $ini)
        <div class="flex items-center justify-between gap-2 rounded-lg bg-surface px-2.5 py-2 text-xs">
            <span>{{ $ini->title }}@if ($ini->review_note)<span class="block text-[11px] text-muted">{{ $ini->review_note }}</span>@endif</span>
            <span class="chip {{ $ini->statusClass() }}">{{ $ini->statusLabel() }}</span>
        </div>
    @endforeach
</section>
