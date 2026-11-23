<div class="grid grid-cols-3 gap-4">
    <div class="col-span-2 flex flex-col gap-4">
        {{-- Status pairing perangkat --}}
        @php
            $state = $connection['state'];
            $ui = [
                'connected' => ['bg-emerald-50 border-emerald-200', 'bg-emerald-500', 'text-emerald-800', 'Terhubung', 'Perangkat WhatsApp sudah ditautkan. Notifikasi siap dikirim.'],
                'needs_scan' => ['bg-amber-50 border-amber-200', 'bg-amber-500', 'text-amber-800', 'Belum terhubung — perlu scan QR', $connection['message'] ?? 'Perangkat belum ditautkan.'],
                'unreachable' => ['bg-rose-50 border-rose-200', 'bg-rose-500', 'text-rose-800', 'Gateway tidak dapat dihubungi', $connection['message'] ?? 'Periksa URL API dan pastikan gateway berjalan.'],
                'unknown' => ['bg-slate-50 border-line', 'bg-slate-400', 'text-slate-700', 'Memeriksa status…', 'Menghubungi gateway untuk mengecek perangkat.'],
                'unconfigured' => ['bg-slate-50 border-line', 'bg-slate-400', 'text-slate-700', 'Belum dikonfigurasi', 'Isi API URL dan nama session lalu simpan.'],
            ][$state] ?? null;
        @endphp
        <section class="card flex items-center gap-4 border {{ $ui[0] }}" data-test="wa-connection"
            @if ($configured) wire:init="checkConnection" wire:poll.60s="checkConnection" @endif>
            <span class="relative flex size-4 shrink-0">
                @if (in_array($state, ['connected', 'unknown'], true))<span class="absolute inline-flex size-full animate-ping rounded-full opacity-60 {{ $ui[1] }}"></span>@endif
                <span class="relative inline-flex size-4 rounded-full {{ $ui[1] }}"></span>
            </span>
            <div class="min-w-0 flex-1">
                <div class="text-xs font-semibold text-muted uppercase">Status pairing WhatsApp</div>
                <div class="text-base font-bold {{ $ui[2] }}">{{ $ui[3] }}</div>
                <div class="text-xs text-muted">{{ $ui[4] }}@if ($connection['checked_at']) · dicek {{ \Illuminate\Support\Carbon::parse($connection['checked_at'])->diffForHumans() }}@endif</div>
                @if ($configured)<div class="mt-0.5 font-mono text-[11px] text-muted">Session: {{ $session }}</div>@endif
            </div>
            @if ($configured)
                <div class="flex shrink-0 flex-col gap-1.5">
                    @if ($state !== 'connected')
                        <button type="button" wire:click="openPairing" class="btn-ok btn-sm">Scan QR sekarang</button>
                    @endif
                    <button type="button" wire:click="checkConnection(true)" wire:loading.attr="disabled" wire:target="checkConnection" class="btn-outline btn-sm">
                        <span wire:loading.remove wire:target="checkConnection">Cek status</span><span wire:loading wire:target="checkConnection">Mengecek…</span>
                    </button>
                </div>
            @endif
        </section>

        <form wire:submit="save" class="card grid grid-cols-2 gap-3">
            <div class="col-span-2 flex items-center justify-between">
                <h2 class="card-title">WhatsApp API (WA Gateway)</h2>
                <span class="chip {{ $enabled && $configured ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $enabled && $configured ? 'Notifikasi aktif' : 'Notifikasi nonaktif' }}</span>
            </div>
            <div class="col-span-2"><label class="label">API URL</label><input type="url" wire:model="host" class="field" placeholder="https://wa-gateway.domain.go.id">@error('host')<p class="error">{{ $message }}</p>@enderror</div>
            <div><label class="label">API Session Name</label><input wire:model="session" class="field" placeholder="brief">@error('session')<p class="error">{{ $message }}</p>@enderror</div>
            <div class="flex flex-col justify-end gap-2">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="enabled" class="accent-navy"> Aktifkan notifikasi WhatsApp</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="remindDirections" class="accent-navy"> Pengingat batas jam instruksi</label>
                <label class="flex items-center gap-2 text-sm" title="Matikan hanya bila gateway memakai sertifikat self-signed"><input type="checkbox" wire:model="verifyTls" class="accent-navy"> Verifikasi sertifikat TLS gateway</label>
            </div>
            <div class="col-span-2 flex flex-wrap gap-2">
                <button class="btn-primary">Simpan</button>
                @if ($configured)
                    <button type="button" wire:click="openPairing" class="btn-ok"><x-icon name="message" size="16" />Scan WhatsApp Session</button>
                    <button type="button" wire:click="logoutDevice" wire:confirm="Putuskan perangkat WhatsApp dari session ini?" class="btn-outline">Putuskan perangkat</button>
                @endif
            </div>
        </form>

        <section class="card">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="card-title">Log pengiriman</h2>
                <span class="text-xs text-muted">Hari ini: {{ $stats['sent'] ?? 0 }} terkirim · {{ $stats['queued'] ?? 0 }} antre · {{ $stats['failed'] ?? 0 }} gagal · {{ $stats['skipped'] ?? 0 }} tidak dikirim</span>
            </div>
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-muted"><tr><th class="py-1.5">Waktu</th><th>Penerima</th><th>Pesan</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($messages as $m)
                        <tr wire:key="wa-{{ $m->id }}" class="border-t border-line align-top">
                            <td class="py-2 text-xs whitespace-nowrap text-muted">{{ $m->created_at->format('d/m H.i') }}</td>
                            <td class="text-xs">{{ $m->user?->name ?? '—' }}<div class="font-mono text-muted">{{ $m->to }}</div></td>
                            <td class="max-w-sm text-xs whitespace-pre-line">{{ str($m->text)->limit(140) }}@if ($m->error)<div class="mt-1 text-rose-600">{{ $m->error }}</div>@endif</td>
                            <td><span class="chip {{ $m->statusClass() }}">{{ $m->statusLabel() }}</span><div class="text-[10px] text-muted">{{ $m->attempts }}x</div></td>
                            <td class="text-right">@if ($m->status === 'failed')<button type="button" wire:click="retry({{ $m->id }})" class="btn-outline btn-sm">Ulangi</button>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-xs text-muted">Belum ada pesan.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-3">{{ $messages->links() }}</div>
        </section>
    </div>

    <div class="flex flex-col gap-4 self-start">
        <section class="card flex flex-col gap-2">
            <h2 class="card-title">Kirim pesan uji</h2>
            <form wire:submit="sendTest" class="flex flex-col gap-2">
                <input wire:model="testNumber" class="field" placeholder="08xxxxxxxxxx" inputmode="tel">
                @error('testNumber')<p class="error">{{ $message }}</p>@enderror
                <button class="btn-outline">Kirim uji</button>
            </form>
        </section>
        <section class="card flex flex-col gap-1.5 text-xs text-muted">
            <h2 class="card-title">Notifikasi yang dikirim</h2>
            <span>• Instruksi baru & teguran ke pejabat/pegawai</span>
            <span>• Surat masuk baru ke Kasubag Umum (paraf)</span>
            <span>• Surat diparaf ke Kepala Dinas (disposisi)</span>
            <span>• Disposisi surat ke setiap penerima</span>
            <span>• Pengingat ke Eselon 3/4 yang belum membuat instruksi sampai batas jam</span>
            @if ($withoutPhone)
                <a href="{{ route('admin.employees') }}" wire:navigate class="mt-2 rounded-lg bg-amber-50 p-2 font-semibold text-amber-800">{{ $withoutPhone }} pegawai aktif belum punya nomor WhatsApp valid → lengkapi di Data Pegawai</a>
            @endif
            <span class="mt-2 rounded-lg bg-surface p-2">Pengiriman lewat antrean: jalankan <code class="font-mono">php artisan queue:work</code> dan <code class="font-mono">php artisan schedule:work</code> (atau cron) di server.</span>
        </section>
    </div>

    <x-modal :show="$showPairing" title="Scan QR WhatsApp" close="closePairing" width="max-w-sm">
        {{-- Polling hanya mengecek status; QR tidak diganti otomatis --}}
        <div @if (in_array($pairingStatus, ['waiting', 'stale'], true)) wire:poll.3s="pollPairing" @endif class="flex flex-col items-center gap-3 text-center">
            @if ($pairingStatus === 'connected')
                <x-icon name="ok" size="56" class="text-ok" />
                <p class="font-bold text-ok-dark">🟢 Perangkat sudah terhubung</p>
                <button type="button" wire:click="closePairing" class="btn-ok btn-sm">Tutup</button>
            @elseif ($qr)
                <p class="text-sm font-semibold text-amber-700">🟡 Menunggu scan QR</p>
                {{-- wire:ignore: polling status tidak boleh menggambar ulang (menghapus) gambar QR; QR baru → key baru → diganti --}}
                <div wire:key="qr-{{ md5($qr) }}" wire:ignore x-data x-init="QRCode.toDataURL(@js($qr), { width: 260, margin: 1 }).then(url => $refs.img.src = url)">
                    <img x-ref="img" alt="QR WhatsApp" class="size-64 rounded-lg border border-line">
                </div>
                <p class="text-xs text-muted">Buka WhatsApp di HP → Perangkat tertaut → Tautkan perangkat, lalu scan QR ini. Status akan berubah otomatis setelah berhasil.</p>
                <p wire:key="qr-timer-{{ $qrIssuedAt }}" wire:ignore class="text-[11px] text-muted" x-data="{ left: Math.max(0, 60 - (Math.floor(Date.now() / 1000) - {{ $qrIssuedAt ?? 0 }})) }" x-init="setInterval(() => left = Math.max(0, left - 1), 1000)">
                    QR tidak akan berganti sendiri.
                    <span x-show="left > 0">Sisa waktu scan ± <b x-text="left"></b> detik.</span>
                    <span x-show="left === 0" class="font-semibold text-rose-600">QR mungkin sudah kedaluwarsa — klik <b>Buat QR baru</b>.</span>
                </p>
            @elseif ($pairingStatus === 'idle')
                <p class="text-sm font-semibold text-muted">Menghubungi gateway…</p>
            @endif
            @if ($pairingError)<p class="text-xs font-semibold text-rose-600">{{ $pairingError }}</p>@endif
            @if ($pairingStatus !== 'connected')
                <div class="flex flex-wrap justify-center gap-2">
                    <button type="button" wire:click="resetAndRequestQr" wire:loading.attr="disabled" class="btn-ok btn-sm">Buat QR baru</button>
                </div>
                <span wire:loading wire:target="requestQr,resetAndRequestQr" class="text-[11px] font-semibold text-navy">Meminta QR…</span>
            @endif
        </div>
    </x-modal>
</div>
