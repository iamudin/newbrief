<?php

namespace App\Models\Concerns;

use App\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Data yang memicu notifikasi WhatsApp (surat, disposisi, instruksi, teguran). */
trait HasWhatsappMessages
{
    public function whatsappMessages(): MorphMany
    {
        return $this->morphMany(WhatsappMessage::class, 'subject')->latest('id');
    }

    /** Pesan WA terakhir untuk jenis tertentu (mis. arsip_paraf / arsip_kadis). */
    public function whatsappFor(?string $kind = null): ?WhatsappMessage
    {
        $messages = $this->relationLoaded('whatsappMessages') ? $this->whatsappMessages : $this->whatsappMessages()->get();

        return $kind ? $messages->firstWhere('kind', $kind) : $messages->first();
    }
}
