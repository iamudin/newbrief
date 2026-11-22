<?php

namespace App\Livewire\Staff;

use App\Livewire\Concerns\HandlesCheckinAndInitiative;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::app')]
class Duties extends Component
{
    use HandlesCheckinAndInitiative;

    public function render()
    {
        $position = auth()->user()->position()->load('duties', 'parent.currentAssignment.user');

        return view('livewire.staff.duties', [
            'position' => $position,
            'initiatives' => $this->myInitiatives(),
        ])->title('Tupoksi');
    }
}
