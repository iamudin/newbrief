<?php

namespace App\Models;

use App\Models\Concerns\HasWhatsappMessages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LetterDisposition extends Model
{
    use HasWhatsappMessages;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expectations' => 'array',
            'read_at' => 'datetime',
            'replied_at' => 'datetime',
            'forwarded_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }

    public function fromPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'from_position_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'to_position_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function stateLabel(): string
    {
        return match (true) {
            $this->completed_at !== null => 'Selesai',
            $this->forwarded_at !== null => 'Diteruskan',
            $this->replied_at !== null => 'Dibalas',
            $this->read_at !== null => 'Dibaca',
            default => 'Belum dibaca',
        };
    }

    public function stateClass(): string
    {
        return match (true) {
            $this->completed_at !== null => 'bg-emerald-100 text-emerald-700',
            $this->forwarded_at !== null => 'bg-info-soft text-navy',
            $this->replied_at !== null => 'bg-sky-100 text-sky-700',
            $this->read_at !== null => 'bg-slate-100 text-slate-600',
            default => 'bg-amber-100 text-amber-800',
        };
    }
}
