<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

class BriefNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
        public string $kind = 'info',
        public bool $whatsapp = false,
        public ?Model $whatsappSubject = null,
        public ?string $whatsappKind = null,
    ) {}

    /**
     * Kirim juga ke WhatsApp. $subject = data sumber (surat/disposisi/instruksi/teguran) agar status
     * pengirimannya tampil di daftar; $kind membedakan beberapa pesan untuk data yang sama.
     */
    public function withWhatsApp(?Model $subject = null, ?string $kind = null): static
    {
        $this->whatsapp = true;
        $this->whatsappSubject = $subject;
        $this->whatsappKind = $kind;

        return $this;
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        // Kanal WhatsApp selalu dilalui bila diminta; kanal yang memutuskan kirim atau catat "tidak dikirim".
        return $this->whatsapp ? ['database', WhatsAppChannel::class] : ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'kind' => $this->kind];
    }

    public function toWhatsApp(object $notifiable): string
    {
        return collect([
            '*'.Setting::get('agency_name').' — BRIEF*',
            '',
            '*'.$this->title.'*',
            $this->body,
            $this->url ? "\nBuka: ".$this->url : null,
        ])->filter(fn ($line) => $line !== null)->implode("\n");
    }
}
