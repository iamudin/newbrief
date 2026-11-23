<?php

namespace App\Enums;

enum Level: string
{
    case Kadis = 'kadis';
    case Eselon3 = 'eselon3';
    case Eselon4 = 'eselon4';
    case Staff = 'staff';
    case Arsip = 'arsip';

    public function label(): string
    {
        return match ($this) {
            self::Kadis => 'Kepala Dinas',
            self::Eselon3 => 'Eselon 3',
            self::Eselon4 => 'Eselon 4',
            self::Staff => 'Staff',
            self::Arsip => 'Operator E-Arsip',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Kadis => 'KADIS',
            self::Eselon3 => 'ESELON 3',
            self::Eselon4 => 'ESELON 4',
            self::Staff => 'STAFF',
            self::Arsip => 'E-ARSIP',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Kadis => 1,
            self::Eselon3 => 2,
            self::Eselon4 => 3,
            self::Staff => 4,
            self::Arsip => 5,
        };
    }

    public function child(): ?self
    {
        return match ($this) {
            self::Kadis => self::Eselon3,
            self::Eselon3 => self::Eselon4,
            self::Eselon4 => self::Staff,
            self::Staff, self::Arsip => null,
        };
    }

    /** Kunci pengaturan batas jam kepatuhan. */
    public function deadlineKey(): ?string
    {
        return match ($this) {
            self::Kadis, self::Arsip => null,
            default => 'deadline_'.$this->value,
        };
    }

    /** @return array<string, string> */
    public static function categories(self $level): array
    {
        return match ($level) {
            self::Kadis => ['kadis' => 'Kepala Dinas'],
            self::Eselon3 => ['sekretaris' => 'Sekretaris', 'kabid' => 'Kepala Bidang'],
            self::Eselon4 => ['kasubag' => 'Kasubag', 'kasi' => 'Kasi / Subkoordinator', 'jafung' => 'Jabatan Fungsional'],
            self::Staff => ['administrasi' => 'Administrasi', 'operator' => 'Operator', 'satpam' => 'Satpam', 'cs' => 'Cleaning Service'],
            self::Arsip => ['operator_arsip' => 'Operator E-Arsip'],
        };
    }

    /** Jenjang atasan yang sah untuk jenjang ini (null = tanpa atasan). */
    public function allowedParents(): array
    {
        return match ($this) {
            self::Kadis => [],
            self::Eselon3 => [self::Kadis],
            self::Eselon4 => [self::Eselon3],
            self::Staff => [self::Eselon4],
            // Operator E-Arsip berada di bawah Kasubag Umum (atau pejabat setara) yang memaraf surat.
            self::Arsip => [self::Eselon4, self::Eselon3],
        };
    }

    /** @return array<int, self> */
    public static function compliable(): array
    {
        return [self::Eselon3, self::Eselon4, self::Staff];
    }
}
