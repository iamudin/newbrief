<?php

namespace App\Services;

use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Penempatan pegawai ke jabatan. Riwayat arahan & tugas tersimpan pada jabatan,
 * sehingga pemegang baru otomatis mewarisi riwayat pemegang lama (kecuali staff).
 */
class AssignmentService
{
    public function assign(User $user, Position $position): void
    {
        DB::transaction(function () use ($user, $position) {
            $current = $user->currentAssignment;

            if ($current && $current->position_id === $position->id) {
                return;
            }

            if ($current) {
                $this->end($current);
            }

            if ($holder = $position->currentAssignment) {
                $this->end($holder);
            }

            PositionAssignment::query()->create([
                'position_id' => $position->id,
                'user_id' => $user->id,
                'started_at' => today()->toDateString(),
            ]);
        });

        $user->unsetRelation('currentAssignment');
        $position->unsetRelation('currentAssignment');
    }

    public function release(User $user): void
    {
        if ($user->currentAssignment) {
            $this->end($user->currentAssignment);
            $user->unsetRelation('currentAssignment');
        }
    }

    private function end(PositionAssignment $assignment): void
    {
        // Penempatan yang baru dimulai hari ini dibatalkan saja, agar tidak ada dua pemegang di hari yang sama.
        if ($assignment->started_at->isToday()) {
            $assignment->delete();

            return;
        }

        $assignment->update(['ended_at' => today()->subDay()->toDateString()]);
    }
}
