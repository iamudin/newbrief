<div>
    <x-page-header title="Peta Lokasi Pegawai" subtitle="Lokasi terakhir hari ini saat menindaklanjuti instruksi">
        <div class="mt-4 grid grid-cols-4 gap-1 rounded-xl bg-navy-2 p-1 text-center">
            @foreach (['' => ['Semua', $counts['office'] + $counts['field'] + $counts['outside']], 'office' => ['Kantor', $counts['office']], 'field' => ['Dinas luar', $counts['field']], 'outside' => ['Luar area', $counts['outside']]] as $key => [$label, $n])
                <button type="button" wire:click="$set('zone', '{{ $key }}')" @class(['tab !px-1', 'tab-active' => $zone === $key, '!text-[#C9D3E3]' => $zone !== $key])>{{ $label }} <b>{{ $n }}</b></button>
            @endforeach
        </div>
    </x-page-header>

    <main class="mx-auto flex w-full max-w-6xl flex-col gap-3 px-4 py-4 lg:px-8">
        <div class="relative z-0 overflow-hidden rounded-[14px] border border-line">
            <div wire:key="map-{{ $zone }}" x-data="{
                    init() {
                        const office = @js($office), markers = @js($markers);
                        const map = L.map(this.$refs.map).setView([office.lat, office.lng], 15);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
                        L.circle([office.lat, office.lng], { radius: office.radius, color: '#147346', fillOpacity: 0.08 }).addTo(map).bindPopup(office.name + ' · radius ' + office.radius + ' m');
                        const colors = { office: '#147346', field: '#2563eb', outside: '#e11d48' };
                        const bounds = [[office.lat, office.lng]];
                        markers.forEach(m => {
                            L.circleMarker([m.lat, m.lng], { radius: 9, color: '#fff', weight: 2, fillColor: colors[m.zone], fillOpacity: 1 })
                                .addTo(map)
                                .bindPopup(`<b>${m.name}</b><br>${m.position}<br>${m.time} · ${m.distance ?? '-'} m dari kantor${m.destination ? '<br>Tujuan: ' + m.destination : ''}`);
                            bounds.push([m.lat, m.lng]);
                        });
                        if (bounds.length > 1) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 16 });
                    }
                }">
                <div x-ref="map" class="h-[55dvh] w-full lg:h-[520px]"></div>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 text-[11px] text-muted">
            <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-[#147346]"></span>Radius kantor</span>
            <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-blue-600"></span>Dinas luar (ditetapkan atasan)</span>
            <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-rose-600"></span>Luar area</span>
            <span>{{ $counts['none'] }} pegawai belum merekam lokasi</span>
        </div>

        <section class="card flex flex-col gap-1.5">
            <h2 class="card-title">Daftar lokasi</h2>
            @foreach ($rows->filter(fn ($r) => $r['location'] && (! $zone || $r['location']->zone === $zone)) as $r)
                <div class="flex items-center justify-between gap-2 border-b border-line py-1.5 text-xs last:border-0">
                    <span><b>{{ $r['user']->name }}</b> · {{ $r['position']->name }}</span>
                    <span class="flex items-center gap-2"><span class="text-muted">{{ $r['location']->created_at->format('H.i') }} · {{ $r['location']->distance_m }} m</span><span class="chip {{ \App\Models\LocationLog::zoneClass($r['location']->zone) }}">{{ \App\Models\LocationLog::zoneLabel($r['location']->zone) }}</span></span>
                </div>
            @endforeach
        </section>
    </main>
</div>
