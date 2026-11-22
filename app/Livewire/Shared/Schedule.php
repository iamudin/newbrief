<?php

namespace App\Livewire\Shared;

use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::app')]
class Schedule extends Component
{
    public function render()
    {
        $s = fn (string $k) => Setting::get($k);
        $level = auth()->user()->level()?->value;

        $steps = [
            ['time' => $s('workday_start'), 'until' => $s('deadline_eselon3'), 'actor' => 'Kepala Dinas', 'level' => 'kadis', 'text' => 'Merilis arahan umum dan arahan prioritas untuk Eselon 3.'],
            ['time' => '≤ '.$s('deadline_eselon3'), 'actor' => 'Eselon 3', 'level' => 'eselon3', 'text' => 'Konfirmasi SIAP atas arahan Kepala Dinas dan/atau menurunkan arahan ke Eselon 4.'],
            ['time' => '≤ '.$s('deadline_eselon4'), 'actor' => 'Eselon 4', 'level' => 'eselon4', 'text' => 'Konfirmasi SIAP, lalu meneruskan tugas ke staff dengan checklist (tupoksi / inisiatif / manual).'],
            ['time' => '≤ '.$s('deadline_staff'), 'actor' => 'Staff', 'level' => 'staff', 'text' => 'Tap SIAP di radius kantor / lokasi dinas luar (lokasi direkam sekali).'],
            ['time' => $s('deadline_staff').' – '.$s('report_deadline'), 'actor' => 'Staff', 'level' => 'staff', 'text' => 'Mengerjakan checklist, memperbarui status (Proses / Tunda / Selesai), melampirkan bukti, dan mengirim laporan.'],
            ['time' => '≤ '.$s('verify_deadline'), 'actor' => 'Eselon 4 / Eselon 3', 'level' => 'eselon4', 'text' => 'Memverifikasi laporan bawahan: diterima atau perlu revisi.'],
            ['time' => $s('verify_deadline').' – '.$s('workday_end'), 'actor' => 'Kepala Dinas', 'level' => 'kadis', 'text' => 'Memantau dashboard, kepatuhan, peta, dan memberi teguran bila perlu.'],
        ];

        return view('livewire.shared.schedule', ['steps' => $steps, 'level' => $level])->title('Jadwal');
    }
}
