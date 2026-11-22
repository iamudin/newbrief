<?php

namespace App\Support;

use App\Enums\Level;
use App\Models\User;

class Navigation
{
    /** @return array<int, array{label:string, route:string, icon:string, active:string}> */
    public static function for(User $user): array
    {
        return match ($user->level()) {
            Level::Kadis => [
                ['label' => 'Dashboard', 'route' => 'kadis.dashboard', 'icon' => 'chart', 'active' => 'kadis.dashboard'],
                ['label' => 'Arahan', 'route' => 'kadis.directions', 'icon' => 'pen', 'active' => 'kadis.directions'],
                ['label' => 'Patuh', 'route' => 'kadis.compliance', 'icon' => 'clock', 'active' => 'kadis.compliance'],
                ['label' => 'Pegawai', 'route' => 'kadis.employees', 'icon' => 'users', 'active' => 'kadis.employees'],
                ['label' => 'Peta', 'route' => 'kadis.map', 'icon' => 'pin', 'active' => 'kadis.map'],
            ],
            Level::Eselon3 => [
                ['label' => 'Dashboard', 'route' => 'eselon3.dashboard', 'icon' => 'home', 'active' => 'eselon3.dashboard'],
                ['label' => 'Arahan', 'route' => 'eselon3.directions', 'icon' => 'pen', 'active' => 'eselon3.directions'],
                ['label' => 'Anggota', 'route' => 'members', 'icon' => 'users', 'active' => 'members'],
                ['label' => 'Jadwal', 'route' => 'schedule', 'icon' => 'clock', 'active' => 'schedule'],
            ],
            Level::Eselon4 => [
                ['label' => 'Dashboard', 'route' => 'eselon4.dashboard', 'icon' => 'home', 'active' => 'eselon4.dashboard'],
                ['label' => 'Bagi & Verifikasi', 'route' => 'eselon4.directions', 'icon' => 'check', 'active' => 'eselon4.directions'],
                ['label' => 'Anggota', 'route' => 'members', 'icon' => 'users', 'active' => 'members'],
                ['label' => 'Jadwal', 'route' => 'schedule', 'icon' => 'clock', 'active' => 'schedule'],
            ],
            Level::Staff => [
                ['label' => 'Dashboard', 'route' => 'staff.dashboard', 'icon' => 'home', 'active' => 'staff.dashboard'],
                ['label' => 'Tugas', 'route' => 'staff.tasks', 'icon' => 'check', 'active' => 'staff.tasks'],
                ['label' => 'Tupoksi', 'route' => 'staff.duties', 'icon' => 'doc', 'active' => 'staff.duties'],
            ],
            default => [],
        };
    }

    /** @return array<int, array{label:string, route:string, icon:string}> */
    public static function admin(): array
    {
        return [
            ['label' => 'Ringkasan', 'route' => 'admin.dashboard', 'icon' => 'chart'],
            ['label' => 'Data User', 'route' => 'admin.users', 'icon' => 'key'],
            ['label' => 'Data Pegawai', 'route' => 'admin.employees', 'icon' => 'users'],
            ['label' => 'Struktur Pegawai', 'route' => 'admin.structure', 'icon' => 'tree'],
            ['label' => 'Tupoksi Jabatan', 'route' => 'admin.duties', 'icon' => 'doc'],
            ['label' => 'Pengaturan', 'route' => 'admin.settings', 'icon' => 'cog'],
        ];
    }
}
