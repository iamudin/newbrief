<div class="grid grid-cols-3 gap-4">
    <form wire:submit="save" class="col-span-2 flex flex-col gap-4">
        <section class="card grid grid-cols-2 gap-3">
            <h2 class="card-title col-span-2">Instansi</h2>
            <div class="col-span-2"><label class="label">Nama instansi</label><input wire:model="values.agency_name" class="field"></div>
            <div><label class="label">Pemerintah daerah (kop surat)</label><input wire:model="values.government_name" class="field" placeholder="Pemerintah Kabupaten …"></div>
            <div><label class="label">Kabupaten / kota (tanda tangan)</label><input wire:model="values.regency" class="field"></div>
            <div class="col-span-2 flex items-center gap-3">
                <img src="{{ $logo ? $logo->temporaryUrl() : asset($values['agency_logo'] ?: 'images/logo-brief.png') }}" alt="Logo" class="size-14 rounded-lg border border-line object-contain p-1">
                <div><label class="label">Logo kop lembar disposisi (PNG/JPG, maks 1 MB)</label><input type="file" wire:model="logo" accept="image/png,image/jpeg" class="text-xs">@error('logo')<p class="error">{{ $message }}</p>@enderror</div>
            </div>
        </section>
        <section class="card grid grid-cols-4 gap-3">
            <h2 class="card-title col-span-4">Batas jam kepatuhan (WIB)</h2>
            @foreach (['deadline_eselon3' => 'Eselon 3', 'deadline_eselon4' => 'Eselon 4', 'deadline_staff' => 'Staff'] as $key => $label)
                <div><label class="label">{{ $label }}</label><input type="time" wire:model="values.{{ $key }}" class="field">@error("values.$key")<p class="error">{{ $message }}</p>@enderror</div>
            @endforeach
            <div></div>
            @foreach (['workday_start' => 'Mulai kerja', 'report_deadline' => 'Batas laporan', 'verify_deadline' => 'Batas verifikasi', 'workday_end' => 'Akhir kerja'] as $key => $label)
                <div><label class="label">{{ $label }}</label><input type="time" wire:model="values.{{ $key }}" class="field">@error("values.$key")<p class="error">{{ $message }}</p>@enderror</div>
            @endforeach
        </section>
        <section class="card grid grid-cols-4 gap-3" data-test="dashboard-targets">
            <div class="col-span-4">
                <h2 class="card-title">Target dashboard Kepala Dinas</h2>
                <p class="text-[11px] text-muted">Dipakai untuk warna capaian: hijau ≥ target, kuning ≤ 10 poin di bawah target, merah lebih rendah.</p>
            </div>
            @foreach (['target_siap' => 'SIAP tepat waktu (%)', 'target_lapor' => 'Melapor tepat waktu (%)', 'target_tuntas' => 'Tugas tuntas (%)', 'target_disposisi' => 'Disposisi tepat waktu (%)', 'target_merata' => 'Staf beban ideal (%)'] as $key => $label)
                <div><label class="label">{{ $label }}</label><input type="number" min="1" max="100" wire:model="values.{{ $key }}" class="field">@error("values.$key")<p class="error">{{ $message }}</p>@enderror</div>
            @endforeach
            <div><label class="label">Arahan Kadis disarankan ≤</label><input type="time" wire:model="values.deadline_kadis" class="field">@error('values.deadline_kadis')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Beban ideal min (tugas)</label><input type="number" min="1" max="20" wire:model="values.workload_ideal_min" class="field">@error('values.workload_ideal_min')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Beban ideal maks (tugas)</label><input type="number" min="1" max="20" wire:model="values.workload_ideal_max" class="field">@error('values.workload_ideal_max')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="col-span-2"><label class="label">Batas disposisi surat Biasa/Rahasia (jam sejak diinput)</label><input type="number" min="1" max="168" wire:model="values.disposition_hours" class="field">@error('values.disposition_hours')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="col-span-2"><label class="label">Batas disposisi surat Segera/Sangat Segera (jam)</label><input type="number" min="1" max="168" wire:model="values.disposition_urgent_hours" class="field">@error('values.disposition_urgent_hours')<p class="error">{{ $message }}</p>@enderror</div>
            <p class="col-span-4 -mt-1 text-[11px] text-muted">Batas arahan Kadis hanya ditampilkan di dashboard; Kepala Dinas tetap tidak dinilai kepatuhannya.</p>
        </section>
        <section class="card grid grid-cols-3 gap-3">
            <h2 class="card-title col-span-3">Lokasi kantor & radius</h2>
            <div class="col-span-3"><label class="label">Nama lokasi</label><input wire:model="values.office_name" class="field"></div>
            <div><label class="label">Latitude</label><input wire:model="values.office_latitude" class="field">@error('values.office_latitude')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Longitude</label><input wire:model="values.office_longitude" class="field">@error('values.office_longitude')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">Radius (meter)</label><input type="number" wire:model="values.office_radius" class="field">@error('values.office_radius')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="col-span-3 overflow-hidden rounded-xl border border-line" wire:ignore x-data="{
                    init() {
                        const lat = +$wire.values.office_latitude, lng = +$wire.values.office_longitude;
                        const map = L.map(this.$refs.map).setView([lat, lng], 16);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
                        const marker = L.marker([lat, lng]).addTo(map);
                        const circle = L.circle([lat, lng], { radius: +$wire.values.office_radius, color: '#147346' }).addTo(map);
                        map.on('click', (e) => {
                            marker.setLatLng(e.latlng); circle.setLatLng(e.latlng);
                            $wire.set('values.office_latitude', e.latlng.lat.toFixed(7), false);
                            $wire.set('values.office_longitude', e.latlng.lng.toFixed(7), false);
                            $wire.$refresh();
                        });
                        $wire.$watch('values.office_radius', (r) => circle.setRadius(+r || 0));
                    }
                }">
                <div x-ref="map" class="h-72"></div>
            </div>
            <p class="col-span-3 -mt-1 text-[11px] text-muted">Klik peta untuk memindahkan titik kantor. Akses lokasi di HP memerlukan HTTPS.</p>
        </section>
        <button class="btn-primary self-start">Simpan pengaturan</button>
    </form>

    <section class="card flex flex-col gap-2 self-start">
        <h2 class="card-title">Hari libur</h2>
        <p class="text-[11px] text-muted">Akhir pekan otomatis tidak dinilai.</p>
        <form wire:submit="addHoliday" class="flex flex-col gap-2">
            <input type="date" wire:model="holidayDate" class="field">@error('holidayDate')<p class="error">{{ $message }}</p>@enderror
            <input wire:model="holidayName" class="field" placeholder="Keterangan">@error('holidayName')<p class="error">{{ $message }}</p>@enderror
            <button class="btn-outline">Tambah</button>
        </form>
        @foreach ($holidays as $h)
            <div class="flex items-center justify-between border-t border-line py-1.5 text-sm"><span>{{ $h->date->translatedFormat('d M Y') }} · {{ $h->name }}</span><button type="button" wire:click="deleteHoliday({{ $h->id }})" class="cursor-pointer text-rose-600"><x-icon name="trash" size="14" /></button></div>
        @endforeach
    </section>
</div>
