<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusClass(): string
    {
        return ['sent' => 'bg-emerald-100 text-emerald-700', 'failed' => 'bg-rose-100 text-rose-700'][$this->status] ?? 'bg-amber-100 text-amber-800';
    }
}
