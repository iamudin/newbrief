<div class="flex flex-col gap-4">
    <div class="flex items-center justify-between gap-3">
        <div class="flex gap-2">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama / NIP…" class="field w-72">
            <select wire:model.live="level" class="field w-48"><option value="">Semua jenjang</option>@foreach ($levels as $l)<option value="{{ $l->value }}">{{ $l->label() }}</option>@endforeach</select>
        </div>
        <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" size="16" />Pegawai baru</button>
    </div>
    <div class="card overflow-hidden !p-0">
        <table class="w-full text-sm">
            <thead class="bg-surface text-left text-xs text-muted"><tr><th class="px-4 py-2.5">Nama</th><th>NIP</th><th>Jabatan saat ini</th><th>Jenjang</th><th>Atasan</th><th>Sejak</th><th class="pr-4 text-right">Aksi</th></tr></thead>
            <tbody>
                @forelse ($employees as $e)
                    @php $a = $e->currentAssignment; @endphp
                    <tr wire:key="e-{{ $e->id }}" class="border-t border-line">
                        <td class="px-4 py-2.5 font-semibold">{{ $e->name }} @unless ($e->is_active)<span class="chip bg-rose-100 text-rose-700">nonaktif</span>@endunless<div class="text-xs font-normal text-muted">{{ $e->phone }}</div></td>
                        <td class="font-mono text-xs">{{ $e->nip }}</td>
                        <td>{{ $a?->position?->name ?? '—' }}</td>
                        <td>@if ($a)<span class="chip bg-info-soft text-navy">{{ $a->position->level->label() }}</span>@endif</td>
                        <td class="text-xs text-muted">{{ $a?->position?->parent?->name ?? '—' }}</td>
                        <td class="text-xs text-muted">{{ $a?->started_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="pr-4 text-right whitespace-nowrap">
                            <button type="button" wire:click="$set('historyId', {{ $e->id }})" class="btn-outline btn-sm">Riwayat</button>
                            <button type="button" wire:click="edit({{ $e->id }})" class="btn-outline btn-sm">Ubah / mutasi</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-6 text-center text-muted">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $employees->links() }}

    <x-modal :show="$showForm" :title="$editingId ? 'Ubah pegawai' : 'Pegawai baru'" close="$set('showForm', false)" width="max-w-2xl">
        <form wire:submit="save" class="grid grid-cols-2 gap-3">
            <div class="col-span-2"><label class="label">Nama lengkap & gelar</label><input wire:model="name" class="field">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">NIP (dipakai login)</label><input wire:model="nip" class="field">@error('nip')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Email</label><input wire:model="email" type="email" class="field">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">No. WhatsApp</label><input wire:model="phone" class="field" placeholder="08xxxxxxxxxx" inputmode="tel"><p class="mt-0.5 text-[11px] text-muted">Dipakai untuk notifikasi WhatsApp.</p></div>
            <div><label class="label">Pangkat / golongan</label><input wire:model="rank" class="field" placeholder="mis. Pembina Utama Muda (IV/c)"></div>
            <div><label class="label">Jenis kelamin</label><select wire:model="gender" class="field"><option value="">—</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div>
            <div class="col-span-2"><label class="label">Password {{ $editingId ? '(kosongkan bila tidak diubah)' : 'awal' }}</label><input wire:model="password" type="password" class="field" autocomplete="new-password">@error('password')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="col-span-2">
                <label class="label">Jabatan</label>
                <select wire:model="positionId" class="field">
                    <option value="">— Tidak menjabat —</option>
                    @foreach ($positions as $p)
                        <option value="{{ $p->id }}">[{{ $p->level->label() }}] {{ $p->name }} — {{ $p->holder() ? 'dijabat '.$p->holder()->name : 'KOSONG' }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-muted">Memilih jabatan yang sudah terisi akan memindahkan pemegang lama keluar dari jabatan tersebut. Riwayat arahan & tugas jabatan akan tampil untuk pemegang baru (kecuali jabatan staff).</p>
            </div>
            <button class="btn-primary col-span-2">Simpan</button>
        </form>
    </x-modal>

    <x-modal :show="(bool) $history" :title="'Riwayat jabatan · '.($history?->name ?? '')" close="$set('historyId', null)">
        @foreach ($history?->assignments ?? [] as $a)
            <div class="flex justify-between border-t border-line py-2 text-sm first:border-0"><span>{{ $a->position->name }}</span><span class="text-xs text-muted">{{ $a->started_at->format('d/m/Y') }} – {{ $a->ended_at?->format('d/m/Y') ?? 'sekarang' }}</span></div>
        @endforeach
    </x-modal>
</div>
