<?php

namespace App\Models;

use App\Models\Concerns\HasWhatsappMessages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reprimand extends Model
{
    use HasWhatsappMessages;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['date' => 'date', 'read_at' => 'datetime'];
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
