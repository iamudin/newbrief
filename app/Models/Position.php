<?php

namespace App\Models;

use App\Enums\Level;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Position extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['level' => Level::class];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PositionAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(PositionAssignment::class)->whereNull('ended_at')->latestOfMany('started_at');
    }

    public function duties(): HasMany
    {
        return $this->hasMany(Duty::class)->orderBy('sort_order')->orderBy('id');
    }

    public function holder(): ?User
    {
        return $this->currentAssignment?->user;
    }

    /** Pemegang jabatan pada tanggal tertentu (untuk riwayat). */
    public function holderOn(Carbon|string $date): ?User
    {
        $date = Carbon::parse($date)->toDateString();

        return $this->assignments()
            ->whereDate('started_at', '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull('ended_at')->orWhereDate('ended_at', '>=', $date))
            ->latest('started_at')
            ->first()?->user;
    }

    public function scopeLevel(Builder $query, Level $level): void
    {
        $query->where('level', $level->value);
    }

    /** Seluruh bawahan (langsung maupun tidak langsung). */
    public function descendants(): Collection
    {
        $all = self::query()->with('currentAssignment.user')->get();
        $result = new Collection;
        $queue = [$this->id];

        while ($queue) {
            $parentId = array_shift($queue);
            foreach ($all->where('parent_id', $parentId) as $child) {
                $result->push($child);
                $queue[] = $child->id;
            }
        }

        return $result->sortBy(fn (Position $p) => [$p->level->rank(), $p->sort_order])->values();
    }

    public function isAncestorOf(Position $other): bool
    {
        $cursor = $other->parent;
        $guard = 0;

        while ($cursor && $guard++ < 20) {
            if ($cursor->id === $this->id) {
                return true;
            }
            $cursor = $cursor->parent;
        }

        return false;
    }

    public function label(): string
    {
        return $this->name.($this->unit ? ' · '.$this->unit : '');
    }
}
