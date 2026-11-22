<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DirectionRecipient extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'reported_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function direction(): BelongsTo
    {
        return $this->belongsTo(Direction::class);
    }

    public function toPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'to_position_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('sort_order')->orderBy('id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TaskMessage::class)->oldest();
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function doneCount(): int
    {
        return $this->tasks->where('status', TaskStatus::Selesai)->count();
    }

    public function progressPercent(): int
    {
        $total = $this->tasks->count();

        return $total ? (int) round($this->doneCount() / $total * 100) : 0;
    }

    public function stateLabel(): string
    {
        return match (true) {
            $this->verification_status === 'approved' => 'Diterima',
            $this->verification_status === 'revision' => 'Perlu revisi',
            $this->reported_at !== null => 'Menunggu verifikasi',
            $this->confirmed_at !== null => 'SIAP · dikerjakan',
            default => 'Belum dikonfirmasi',
        };
    }

    public function stateClass(): string
    {
        return match (true) {
            $this->verification_status === 'approved' => 'bg-emerald-100 text-emerald-700',
            $this->verification_status === 'revision' => 'bg-rose-100 text-rose-700',
            $this->reported_at !== null => 'bg-amber-100 text-amber-800',
            $this->confirmed_at !== null => 'bg-sky-100 text-sky-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }
}
