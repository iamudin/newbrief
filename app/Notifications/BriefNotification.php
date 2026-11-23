<?php

namespace App\Notifications;

use App\Models\Setting;
use App\Notifications\Channels\WhatsAppChannel;
use App\Services\WhatsAppService;
use Illuminate\Notifications\Notification;

class BriefNotification extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
        public string $kind = 'info',
        public bool $whatsapp = false,
    ) {}

    /** Kirim juga ke WhatsApp (bila diaktifkan super admin & nomor HP pegawai terisi). */
    public function withWhatsApp(bool $send = true): static
    {
        $this->whatsapp = $send;

        return $this;
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $this->whatsapp && WhatsAppService::isEnabled() && filled($notifiable->phone ?? null)
            ? ['database', WhatsAppChannel::class]
            : ['database'];
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
