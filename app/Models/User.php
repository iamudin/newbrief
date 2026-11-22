<?php

namespace App\Models;

use App\Enums\Level;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PositionAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(PositionAssignment::class)->whereNull('ended_at')->latestOfMany('started_at');
    }

    public function position(): ?Position
    {
        return $this->currentAssignment?->position;
    }

    public function level(): ?Level
    {
        return $this->position()?->level;
    }

    public function isLevel(Level ...$levels): bool
    {
        return in_array($this->level(), $levels, true);
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    public function homeRoute(): string
    {
        if ($this->is_admin) {
            return route('admin.dashboard');
        }

        return match ($this->level()) {
            Level::Kadis => route('kadis.dashboard'),
            Level::Eselon3 => route('eselon3.dashboard'),
            Level::Eselon4 => route('eselon4.dashboard'),
            Level::Staff => route('staff.dashboard'),
            default => route('no-position'),
        };
    }
}
