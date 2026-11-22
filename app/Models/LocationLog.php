<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationLog extends Model
{
    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public static function zoneLabel(?string $zone): string
    {
        return ['office' => 'Radius kantor', 'field' => 'Dinas luar', 'outside' => 'Luar area'][$zone] ?? 'Belum ada lokasi';
    }

    public static function zoneClass(?string $zone): string
    {
        return ['office' => 'bg-emerald-100 text-emerald-700', 'field' => 'bg-sky-100 text-sky-700', 'outside' => 'bg-rose-100 text-rose-700'][$zone] ?? 'bg-slate-100 text-slate-500';
    }
}
