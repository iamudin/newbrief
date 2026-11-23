<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Notifications\Notification;

class WhatsAppChannel
{
    public function __construct(private WhatsAppService $whatsapp) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if ($notifiable instanceof User && method_exists($notification, 'toWhatsApp')) {
            $this->whatsapp->queueToUser(
                $notifiable,
                $notification->toWhatsApp($notifiable),
                $notification->whatsappKind ?? $notification->kind ?? 'info',
                $notification->whatsappSubject ?? null,
            );
        }
    }
}
