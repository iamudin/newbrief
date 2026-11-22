<div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama / NIP…" class="field max-w-sm">
        <button type="button" wire:click="create" class="btn-primary"><x-icon name="plus" size="16" />Akun super admin</button>
    </div>
    <p class="-mt-2 text-xs text-muted">Akun pegawai dibuat dari menu Data Pegawai. Di sini Anda mengatur akses login: NIP, email, password, status aktif, dan hak super admin.</p>
    <div class="card overflow-hidden !p-0">
        <table class="w-full text-sm">
            <thead class="bg-surface text-left text-xs text-muted"><tr><th class="px-4 py-2.5">Nama</th><th>NIP (login)</th><th>Jabatan</th><th>Akses</th><th>Login terakhir</th><th class="pr-4 text-right">Aksi</th></tr></thead>
            <tbody>
                @foreach ($users as $u)
                    <tr wire:key="u-{{ $u->id }}" class="border-t border-line">
                        <td class="px-4 py-2.5 font-semibold">{{ $u->name }}<div class="text-xs font-normal text-muted">{{ $u->email }}</div></td>
                        <td class="font-mono text-xs">{{ $u->nip }}</td>
                        <td class="text-xs">{{ $u->currentAssignment?->position?->name ?? '—' }}</td>
                        <td>
                            @if ($u->is_admin)<span class="chip bg-gold-soft text-[#7A5A00]">Super admin</span>@endif
                            <span class="chip {{ $u->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">{{ $u->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </td>
                        <td class="text-xs text-muted">{{ $u->last_login_at?->diffForHumans() ?? '—' }}</td>
                        <td class="pr-4 text-right whitespace-nowrap">
                            <button type="button" wire:click="edit({{ $u->id }})" class="btn-outline btn-sm">Ubah</button>
                            <button type="button" wire:click="resetPassword({{ $u->id }})" wire:confirm="Buat password baru untuk {{ $u->name }}?" class="btn-outline btn-sm">Reset password</button>
                            @if ($u->id !== auth()->id())<button type="button" wire:click="toggleActive({{ $u->id }})" class="btn-outline btn-sm">{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $users->links() }}

    <x-modal :show="$showForm" :title="$editingId ? 'Ubah akses user' : 'Akun super admin baru'" close="$set('showForm', false)">
        <form wire:submit="save" class="grid grid-cols-2 gap-3">
            <div class="col-span-2"><label class="label">Nama</label><input wire:model="name" class="field">@error('name')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">NIP / username login</label><input wire:model="nip" class="field">@error('nip')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Email</label><input wire:model="email" type="email" class="field">@error('email')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="col-span-2"><label class="label">Password {{ $editingId ? '(kosongkan bila tidak diubah)' : '' }}</label><input wire:model="password" type="password" class="field" autocomplete="new-password">@error('password')<p class="error">{{ $message }}</p>@enderror</div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="isAdmin" class="accent-navy"> Super admin</label>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="isActive" class="accent-navy"> Akun aktif</label>
            @error('isAdmin')<p class="error col-span-2">{{ $message }}</p>@enderror
            <button class="btn-primary col-span-2">Simpan</button>
        </form>
    </x-modal>
</div>
