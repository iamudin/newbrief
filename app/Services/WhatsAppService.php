<?php

namespace App\Services;

use App\Jobs\SendWhatsApp;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Klien WA gateway (mengikuti referensi e-office WaSender):
 *  POST {host}/message/send-text  {session, to, text, is_group}
 *  POST {host}/session/start      {session}  → {status: connected} | {qr: "..."}
 *  POST {host}/session/logout     {session}
 *  GET  {host}/session/{session}  → {data: {status: connected|connecting|disconnected}}  (hanya baca, tidak membuat QR baru)
 * Host & session diatur super admin di menu Pengaturan WhatsApp.
 */
class WhatsAppService
{
    /** Batas pesan WhatsApp per penerima per jam (mencegah spam / nomor diblokir WhatsApp). */
    public const HOURLY_LIMIT = 20;

    private static function recentCount(int $userId): int
    {
        return WhatsappMessage::query()->where('user_id', $userId)->whereIn('status', ['queued', 'sent'])->where('created_at', '>=', now()->subHour())->count();
    }

    public static function isConfigured(): bool
    {
        return filled(Setting::get('wa_host')) && filled(Setting::get('wa_session'));
    }

    public static function isEnabled(): bool
    {
        return Setting::get('wa_enabled') === '1' && self::isConfigured();
    }

    public static function formatPhone(?string $number): ?string
    {
        $number = preg_replace('/[^0-9]/', '', (string) $number);

        if ($number === '') {
            return null;
        }

        if (str_starts_with($number, '0')) {
            $number = '62'.substr($number, 1);
        } elseif (str_starts_with($number, '8')) {
            $number = '62'.$number;
        }

        return strlen($number) >= 10 ? $number : null;
    }

    /**
     * Antrekan pesan ke pengguna. Bila tidak bisa dikirim (WA nonaktif, nomor kosong/tidak valid,
     * akun nonaktif) tetap dicatat berstatus "skipped" beserta alasannya agar terlihat di daftar.
     */
    public function queueToUser(User $user, string $text, string $kind = 'info', ?Model $subject = null): WhatsappMessage
    {
        $to = self::formatPhone($user->phone);

        $skipReason = match (true) {
            ! self::isConfigured() => 'Gateway WhatsApp belum dikonfigurasi.',
            ! self::isEnabled() => 'Notifikasi WhatsApp sedang dinonaktifkan.',
            blank($user->phone) => 'Nomor WhatsApp '.$user->name.' belum diisi.',
            ! $to => 'Nomor WhatsApp '.$user->name.' tidak valid.',
            ! $user->is_active => 'Akun '.$user->name.' nonaktif.',
            self::recentCount($user->id) >= self::HOURLY_LIMIT => 'Batas '.self::HOURLY_LIMIT.' pesan/jam ke '.$user->name.' tercapai (anti-spam).',
            default => null,
        };

        if ($skipReason) {
            return WhatsappMessage::query()->create([
                'user_id' => $user->id,
                'to' => $to ?? '-',
                'text' => $text,
                'kind' => $kind,
                'status' => 'skipped',
                'error' => $skipReason,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
            ]);
        }

        return $this->queue($to, $text, $kind, $user->id, $subject);
    }

    public function queue(string $to, string $text, string $kind = 'info', ?int $userId = null, ?Model $subject = null): WhatsappMessage
    {
        $message = WhatsappMessage::query()->create([
            'user_id' => $userId,
            'to' => $to,
            'text' => $text,
            'kind' => $kind,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
        ]);

        try {
            SendWhatsApp::dispatch($message->id);
        } catch (\Throwable $e) {
            // Gateway gagal saat antrean sinkron: jangan gagalkan aksi pengguna, cukup catat.
            $message->update(['status' => 'failed', 'error' => str($e->getMessage())->limit(300)]);
        }

        return $message->refresh();
    }

    public function sendText(string $to, string $text): Response
    {
        return $this->post('/message/send-text', [
            'session' => Setting::get('wa_session'),
            'to' => $to,
            'text' => $text,
            'is_group' => false,
        ]);
    }

    /** @return array{status?: string, qr?: string, error?: string} */
    public function startSession(): array
    {
        return $this->safeJson(fn () => $this->post('/session/start', ['session' => Setting::get('wa_session')]));
    }

    /**
     * Status perangkat terakhir yang tersimpan.
     *
     * @return array{state: string, message: ?string, checked_at: ?string}
     */
    public static function connection(): array
    {
        if (! self::isConfigured()) {
            return ['state' => 'unconfigured', 'message' => null, 'checked_at' => null];
        }

        return [
            'state' => Setting::get('wa_connection') ?: 'unknown',
            'message' => Setting::get('wa_connection_message'),
            'checked_at' => Setting::get('wa_connection_checked_at'),
        ];
    }

    /** Simpan status koneksi: connected | needs_scan | unreachable. */
    public static function rememberConnection(string $state, ?string $message = null): void
    {
        Setting::put('wa_connection', $state);
        Setting::put('wa_connection_message', $message);
        Setting::put('wa_connection_checked_at', now()->toDateTimeString());
    }

    /**
     * Status session dari gateway TANPA memulai/mereset session (tidak membuat QR baru).
     *
     * @return array{status: string, error?: string} status: connected | connecting | disconnected | not_found | unknown
     */
    public function sessionStatus(): array
    {
        try {
            $response = self::http()->timeout(10)
                ->get(rtrim((string) Setting::get('wa_host'), '/').'/session/'.rawurlencode((string) Setting::get('wa_session')));
        } catch (\Throwable $e) {
            return ['status' => 'unknown', 'error' => 'Tidak dapat terhubung ke gateway: '.$e->getMessage()];
        }

        if ($response->status() === 404) {
            return ['status' => 'not_found'];
        }

        if (! $response->successful()) {
            return ['status' => 'unknown', 'error' => 'Gateway merespons HTTP '.$response->status().'.'];
        }

        $status = $response->json('data.status') ?? $response->json('status');

        if ($response->json('data.connection.isConnected') === true) {
            $status = 'connected';
        }

        return ['status' => in_array($status, ['connected', 'connecting', 'disconnected'], true) ? $status : 'unknown'];
    }

    /**
     * Periksa & simpan status perangkat (hanya membaca status, aman dipanggil berkala).
     *
     * @return array{state: string, message: ?string, checked_at: ?string}
     */
    public function checkConnection(): array
    {
        if (! self::isConfigured()) {
            return self::connection();
        }

        $result = $this->sessionStatus();

        [$state, $message] = match (true) {
            isset($result['error']) => ['unreachable', $result['error']],
            $result['status'] === 'connected' => ['connected', null],
            $result['status'] === 'connecting' => ['needs_scan', 'Menunggu scan QR.'],
            default => ['needs_scan', 'Perangkat belum ditautkan. Scan QR untuk menghubungkan.'],
        };

        self::rememberConnection($state, $message);

        return self::connection();
    }

    /** @return array<string, mixed> */
    public function logoutSession(): array
    {
        return $this->safeJson(fn () => $this->post('/session/logout', ['session' => Setting::get('wa_session')]));
    }

    private function post(string $path, array $payload): Response
    {
        return self::http()
            ->timeout(15)
            ->post(rtrim((string) Setting::get('wa_host'), '/').$path, $payload);
    }

    /** Klien HTTP ke gateway: verifikasi sertifikat TLS aktif kecuali dimatikan admin (gateway self-signed). */
    private static function http(): PendingRequest
    {
        $client = Http::acceptJson()->withOptions(['allow_redirects' => false]);

        return Setting::get('wa_verify_tls') === '0' ? $client->withoutVerifying() : $client;
    }

    private function safeJson(callable $request): array
    {
        try {
            $response = $request();

            return $response->json() ?? ['error' => 'Respons gateway tidak valid (HTTP '.$response->status().').'];
        } catch (\Throwable $e) {
            return ['error' => 'Tidak dapat terhubung ke gateway: '.$e->getMessage()];
        }
    }
}
