<?php

namespace App\Enums;

enum Compliance: string
{
    case Patuh = 'patuh';
    case Terlambat = 'terlambat';
    case Tidak = 'tidak';
    case Menunggu = 'menunggu';
    case Libur = 'libur';

    public function label(): string
    {
        return match ($this) {
            self::Patuh => 'Patuh',
            self::Terlambat => 'Terlambat',
            self::Tidak => 'Tidak patuh',
            self::Menunggu => 'Menunggu',
            self::Libur => 'Hari libur',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Patuh => 'bg-emerald-100 text-emerald-700',
            self::Terlambat => 'bg-amber-100 text-amber-800',
            self::Tidak => 'bg-rose-100 text-rose-700',
            self::Menunggu => 'bg-slate-100 text-slate-600',
            self::Libur => 'bg-slate-100 text-slate-500',
        };
    }

    public function isViolation(): bool
    {
        return $this === self::Terlambat || $this === self::Tidak;
    }
}
