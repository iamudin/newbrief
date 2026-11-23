<?php

namespace App\Jobs;

use App\Models\WhatsappMessage;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/** Kirim satu pesan WhatsApp lewat gateway; dicoba ulang 3x dengan jeda (bukan berulang tanpa batas). */
class SendWhatsApp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public int $messageId) {}

    public function handle(WhatsAppService $whatsapp): void
    {
        $message = WhatsappMessage::query()->find($this->messageId);

        if (! $message || $message->status === 'sent') {
            return;
        }

        $message->increment('attempts');
        $response = $whatsapp->sendText($message->to, $message->text);

        if (! $response->successful()) {
            $message->update(['error' => 'HTTP '.$response->status().': '.str($response->body())->limit(300)]);

            throw new RuntimeException('Gagal mengirim WhatsApp ke '.$message->to);
        }

        $message->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
    }

    public function failed(?\Throwable $e): void
    {
        WhatsappMessage::query()->whereKey($this->messageId)->update([
            'status' => 'failed',
            'error' => str($e?->getMessage() ?? 'Gagal')->limit(300),
        ]);
    }
}
