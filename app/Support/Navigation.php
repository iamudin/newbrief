<?php

namespace App\Support;

use App\Enums\Level;
use App\Models\User;
use App\Services\ArsipService;

class Navigation
{
    /** @return array<int, array{label:string, route:string, icon:string, active:string}> */
    public static function for(User $user): array
    {
        $arsip = ['label' => 'E-Arsip', 'route' => 'arsip.index', 'icon' => 'archive', 'active' => 'arsip.*', 'badge' => ArsipService::pendingCount($user)];

        $items = match ($user->level()) {
            Level::Kadis => [
                ['label' => 'Dashboard', 'route' => 'kadis.dashboard', 'icon' => 'chart', 'active' => ['kadis.dashboard', 'kadis.metric']],
                ['label' => 'Arahan', 'route' => 'kadis.directions', 'icon' => 'pen', 'active' => 'kadis.directions'],
                ['label' => 'Patuh', 'route' => 'kadis.compliance', 'icon' => 'clock', 'active' => 'kadis.compliance'],
                ['label' => 'Pegawai', 'route' => 'kadis.employees', 'icon' => 'users', 'active' => 'kadis.employees'],
                ['label' => 'Peta', 'route' => 'kadis.map', 'icon' => 'pin', 'active' => 'kadis.map'],
                $arsip,
            ],
            Level::Eselon3 => [
                ['label' => 'Dashboard', 'route' => 'eselon3.dashboard', 'icon' => 'home', 'active' => 'eselon3.dashboard'],
                ['label' => 'Arahan', 'route' => 'eselon3.directions', 'icon' => 'pen', 'active' => 'eselon3.directions'],
                ['label' => 'Anggota', 'route' => 'members', 'icon' => 'users', 'active' => 'members'],
                $arsip,
                ['label' => 'Jadwal', 'route' => 'schedule', 'icon' => 'clock', 'active' => 'schedule'],
            ],
            Level::Eselon4 => [
                ['label' => 'Dashboard', 'route' => 'eselon4.dashboard', 'icon' => 'home', 'active' => 'eselon4.dashboard'],
                ['label' => 'Bagi & Verifikasi', 'route' => 'eselon4.directions', 'icon' => 'check', 'active' => 'eselon4.directions'],
                ['label' => 'Anggota', 'route' => 'members', 'icon' => 'users', 'active' => 'members'],
                $arsip,
                ['label' => 'Jadwal', 'route' => 'schedule', 'icon' => 'clock', 'active' => 'schedule'],
            ],
            Level::Staff => [
                ['label' => 'Dashboard', 'route' => 'staff.dashboard', 'icon' => 'home', 'active' => 'staff.dashboard'],
                ['label' => 'Tugas', 'route' => 'staff.tasks', 'icon' => 'check', 'active' => 'staff.tasks'],
                ['label' => 'Tupoksi', 'route' => 'staff.duties', 'icon' => 'doc', 'active' => 'staff.duties'],
                $arsip,
            ],
            Level::Arsip => [
                ['label' => 'Surat Masuk', 'route' => 'arsip.index', 'icon' => 'archive', 'active' => ['arsip.index', 'arsip.show', 'arsip.edit']],
                ['label' => 'Input Surat', 'route' => 'arsip.create', 'icon' => 'plus', 'active' => 'arsip.create'],
            ],
            default => [],
        };

        return $items;
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
            ['label' => 'WhatsApp', 'route' => 'admin.whatsapp', 'icon' => 'message'],
            ['label' => 'Pengaturan', 'route' => 'admin.settings', 'icon' => 'cog'],
        ];
    }
}
