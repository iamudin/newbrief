<div class="flex flex-col gap-6">
    <div class="grid grid-cols-4 gap-4">
        <x-stat label="Pegawai aktif" :value="$employees" />
        <x-stat label="Jabatan" :value="$positions" :hint="$vacant->count().' jabatan kosong'" />
        <x-stat label="Akun nonaktif" :value="$inactive" tone="warn" />
        <x-stat label="Jabatan tanpa tupoksi" :value="$withoutDuties" tone="gold" />
    </div>
    <div class="grid grid-cols-2 gap-4">
        <section class="card">
            <h2 class="card-title mb-3">Kepatuhan hari ini per jenjang</h2>
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs text-muted"><th class="py-1">Jenjang</th><th>Jabatan</th><th>Patuh</th><th>Pelanggaran</th></tr></thead>
                <tbody>
                    @foreach ($byLevel as $row)
                        <tr class="border-t border-line"><td class="py-2 font-semibold">{{ $row['label'] }}</td><td>{{ $row['count'] }}</td><td>{{ $row['rated'] ? $row['patuh'] : '—' }}</td><td class="text-rose-600">{{ $row['rated'] ? $row['violations'] : '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>
        <section class="card">
            <h2 class="card-title mb-3">Jabatan kosong</h2>
            @forelse ($vacant as $p)
                <div class="flex justify-between border-t border-line py-2 text-sm first:border-0"><span>{{ $p->name }}</span><span class="text-xs text-muted">{{ $p->parent?->name }}</span></div>
            @empty
                <x-empty>Semua jabatan terisi.</x-empty>
            @endforelse
            <a href="{{ route('admin.employees') }}" wire:navigate class="btn-outline btn-sm mt-3">Kelola penempatan pegawai</a>
        </section>
    </div>
</div>
