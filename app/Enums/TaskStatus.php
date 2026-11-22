<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case Proses = 'proses';
    case Tunda = 'tunda';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Belum',
            self::Proses => 'Proses',
            self::Tunda => 'Tunda',
            self::Selesai => 'Selesai',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-slate-100 text-slate-600',
            self::Proses => 'bg-sky-100 text-sky-700',
            self::Tunda => 'bg-amber-100 text-amber-800',
            self::Selesai => 'bg-emerald-100 text-emerald-700',
        };
    }
}
