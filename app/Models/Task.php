<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => TaskStatus::class, 'status_updated_at' => 'datetime'];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(DirectionRecipient::class, 'direction_recipient_id');
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            'duty' => 'Tupoksi',
            'initiative' => 'Inisiatif',
            default => 'Dari atasan',
        };
    }

    public function sourceClass(): string
    {
        return match ($this->source) {
            'duty' => 'bg-[#E3F2EA] text-[#0B6242]',
            'initiative' => 'bg-[#FBF3DC] text-[#7A5A00]',
            default => 'bg-[#DCE5F2] text-[#0E2B4B]',
        };
    }
}
