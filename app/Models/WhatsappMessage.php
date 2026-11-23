<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function statusClass(): string
    {
        return [
            'sent' => 'bg-emerald-100 text-emerald-700',
            'failed' => 'bg-rose-100 text-rose-700',
            'skipped' => 'bg-slate-100 text-slate-500',
        ][$this->status] ?? 'bg-amber-100 text-amber-800';
    }

    public function statusLabel(): string
    {
        return [
            'sent' => 'WA terkirim',
            'failed' => 'WA gagal',
            'skipped' => 'WA tidak dikirim',
        ][$this->status] ?? 'WA antre';
    }
}
