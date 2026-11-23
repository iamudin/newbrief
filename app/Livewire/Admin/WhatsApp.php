<?php

namespace App\Livewire\Admin;

use App\Jobs\SendWhatsApp;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\WhatsAppService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/** Konfigurasi WA gateway + pairing QR (mengikuti referensi appconfig e-office). */
#[Layout('layouts::admin')]
class WhatsApp extends Component
{
    use WithPagination;

    public bool $enabled = false;

    public bool $remindDirections = true;

    public bool $verifyTls = true;

    public string $host = '';

    public string $session = '';

    public bool $showPairing = false;

    public string $pairingStatus = 'idle'; // idle | waiting | connected | error

    public ?string $qr = null;

    public ?string $pairingError = null;

    public ?int $qrIssuedAt = null;

    public string $testNumber = '';

    public function mount(): void
    {
        $this->enabled = Setting::get('wa_enabled') === '1';
        $this->remindDirections = Setting::get('wa_remind_directions') === '1';
        $this->verifyTls = Setting::get('wa_verify_tls') !== '0';
        $this->host = (string) Setting::get('wa_host');
        $this->session = (string) Setting::get('wa_session');
    }

    public function save(): void
    {
        $this->validate([
            'host' => ['nullable', 'url:https,http', 'max:255'],
            'session' => 'required|string|max:100|regex:/^[A-Za-z0-9_-]+$/',
        ], ['session.regex' => 'Nama session hanya huruf, angka, - dan _.'], ['host' => 'URL API', 'session' => 'nama session']);

        if ($this->enabled && blank($this->host)) {
            $this->addError('host', 'Isi URL API gateway sebelum mengaktifkan WhatsApp.');

            return;
        }

        Setting::put('wa_host', rtrim($this->host, '/'));
        Setting::put('wa_session', $this->session);
        Setting::put('wa_enabled', $this->enabled ? '1' : '0');
        Setting::put('wa_remind_directions', $this->remindDirections ? '1' : '0');
        Setting::put('wa_verify_tls', $this->verifyTls ? '1' : '0');
        Setting::put('wa_connection', null); // gateway/session bisa berubah → cek ulang
        $this->dispatch('toast', message: 'Pengaturan WhatsApp disimpan.');
    }

    /** Cek status perangkat ke gateway (otomatis saat halaman dibuka & berkala, atau manual). */
    public function checkConnection(bool $manual = false): void
    {
        $last = Setting::get('wa_connection_checked_at');

        // Hindari memanggil gateway terlalu sering saat pemeriksaan otomatis.
        if (! $manual && Setting::get('wa_connection') && $last && now()->diffInSeconds($last, true) < 45) {
            return;
        }

        $status = app(WhatsAppService::class)->checkConnection();

        if ($manual) {
            $this->dispatch('toast', message: match ($status['state']) {
                'connected' => 'WhatsApp terhubung.',
                'needs_scan' => 'Belum terhubung — perlu scan QR.',
                'unreachable' => 'Gateway tidak dapat dihubungi.',
                default => 'Gateway belum dikonfigurasi.',
            }, tone: $status['state'] === 'connected' ? 'ok' : 'error');
        }
    }

    /**
     * Buka jendela pairing. QR diminta SEKALI; selanjutnya hanya status yang dipantau
     * (tanpa memanggil /session/start lagi) agar QR tidak berganti sebelum sempat di-scan.
     */
    public function openPairing(WhatsAppService $whatsapp): void
    {
        $this->showPairing = true;
        $this->reset('qr', 'pairingError');

        if (! WhatsAppService::isConfigured()) {
            $this->pairingStatus = 'error';
            $this->pairingError = 'Simpan URL API dan nama session terlebih dahulu.';

            return;
        }

        if ($whatsapp->checkConnection()['state'] === 'connected') {
            $this->pairingStatus = 'connected';

            return;
        }

        $this->requestQr($whatsapp);
    }

    public function closePairing(): void
    {
        $this->reset('showPairing', 'qr', 'pairingError');
        $this->pairingStatus = 'idle';
    }

    /**
     * Minta QR ke gateway. Gateway hanya mengirim QR saat session baru dibuat ("Session already exist"
     * bila sudah ada), jadi bila belum terhubung session direset SEKALI lalu dimulai ulang —
     * hanya saat jendela dibuka / tombol diklik, tidak pernah dari polling.
     */
    public function requestQr(WhatsAppService $whatsapp, bool $allowReset = true): void
    {
        $result = $whatsapp->startSession();

        if (empty($result['qr']) && ($result['status'] ?? null) !== 'connected' && $allowReset
            && $whatsapp->checkConnection()['state'] !== 'connected') {
            $whatsapp->logoutSession();
            usleep(800_000); // beri jeda gateway membersihkan session lama
            $result = $whatsapp->startSession();
        }

        if (isset($result['error']) && empty($result['qr'])) {
            // Gateway menolak start (mis. session sudah berjalan) → cek status, bisa jadi sudah terhubung.
            if ($whatsapp->checkConnection()['state'] === 'connected') {
                $this->pairingStatus = 'connected';

                return;
            }

            $this->pairingStatus = 'error';
            $this->pairingError = $result['error'];

            return;
        }

        if (($result['status'] ?? null) === 'connected') {
            WhatsAppService::rememberConnection('connected');
            $this->pairingStatus = 'connected';
            $this->qr = null;

            return;
        }

        if (! empty($result['qr'])) {
            WhatsAppService::rememberConnection('needs_scan', 'Menunggu scan QR.');
            $this->pairingStatus = 'waiting';
            $this->qr = $result['qr'];
            $this->qrIssuedAt = now()->timestamp;
            $this->pairingError = null;

            return;
        }

        $this->pairingStatus = 'stale';
        $this->qr = null;
        $this->pairingError = 'Gateway belum mengirim QR'.(isset($result['message']) ? ' ('.$result['message'].')' : '').'. Klik "Buat QR baru" untuk mencoba lagi.';
    }

    /** Reset session lalu minta QR baru — hanya atas klik admin. */
    public function resetAndRequestQr(WhatsAppService $whatsapp): void
    {
        $whatsapp->logoutSession();
        usleep(800_000);
        $this->requestQr($whatsapp, allowReset: false);
    }

    /** Polling selama jendela terbuka: hanya membaca status, TIDAK membuat QR baru. */
    public function pollPairing(WhatsAppService $whatsapp): void
    {
        if (! $this->showPairing || $this->pairingStatus === 'connected') {
            return;
        }

        if ($whatsapp->checkConnection()['state'] === 'connected') {
            $this->pairingStatus = 'connected';
            $this->qr = null;
            $this->pairingError = null;
            $this->dispatch('toast', message: 'WhatsApp berhasil terhubung.');
        }
    }

    public function logoutDevice(WhatsAppService $whatsapp): void
    {
        $result = $whatsapp->logoutSession();

        if (! isset($result['error'])) {
            WhatsAppService::rememberConnection('needs_scan', 'Perangkat diputus. Scan QR untuk menghubungkan kembali.');
        }

        $this->qr = null;
        $this->pairingStatus = 'idle';
        $this->dispatch('toast', message: isset($result['error']) ? $result['error'] : 'Perangkat WhatsApp diputus.', tone: isset($result['error']) ? 'error' : 'ok');
    }

    public function sendTest(WhatsAppService $whatsapp): void
    {
        $this->validate(['testNumber' => 'required|string|max:20'], attributes: ['testNumber' => 'nomor tujuan']);
        $to = WhatsAppService::formatPhone($this->testNumber);

        if (! $to || ! WhatsAppService::isConfigured()) {
            $this->addError('testNumber', $to ? 'Simpan konfigurasi gateway terlebih dahulu.' : 'Nomor tidak valid.');

            return;
        }

        $whatsapp->queue($to, '*BRIEF* — Pesan uji notifikasi WhatsApp. Jika pesan ini diterima, konfigurasi sudah benar.', 'test');
        $this->dispatch('toast', message: "Pesan uji diantrekan ke {$to}. Pastikan queue worker berjalan.");
    }

    public function retry(int $id): void
    {
        $message = WhatsappMessage::query()->findOrFail($id);
        $message->update(['status' => 'queued', 'error' => null]);
        SendWhatsApp::dispatch($message->id);
    }

    public function render()
    {
        return view('livewire.admin.whatsapp', [
            'messages' => WhatsappMessage::query()->with('user')->latest('id')->paginate(15),
            'stats' => WhatsappMessage::query()->whereDate('created_at', today())->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
            'withoutPhone' => User::query()->where('is_admin', false)->where('is_active', true)->whereHas('currentAssignment')->get()->filter(fn (User $u) => ! WhatsAppService::formatPhone($u->phone))->count(),
            'configured' => WhatsAppService::isConfigured(),
            'connection' => WhatsAppService::connection(),
        ])->title('WhatsApp');
    }
}
