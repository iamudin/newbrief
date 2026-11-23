<?php

namespace App\Livewire\Admin;

use App\Enums\Compliance;
use App\Enums\Level;
use App\Models\Position;
use App\Models\User;
use App\Services\ComplianceService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class Dashboard extends Component
{
    public function render(ComplianceService $compliance)
    {
        $positions = Position::query()->with('currentAssignment.user', 'parent')->get();
        $today = $compliance->forDate(today());

        return view('livewire.admin.dashboard', [
            'employees' => User::query()->where('is_admin', false)->where('is_active', true)->count(),
            'inactive' => User::query()->where('is_active', false)->count(),
            'positions' => $positions->count(),
            'vacant' => $positions->filter(fn ($p) => ! $p->currentAssignment),
            'withoutDuties' => Position::query()->doesntHave('duties')->count(),
            'byLevel' => collect(Level::cases())->map(fn (Level $l) => [
                'label' => $l->label(),
                'count' => $positions->where('level', $l)->count(),
                'patuh' => $today->where('level', $l)->where('status', Compliance::Patuh)->count(),
                'violations' => $today->where('level', $l)->filter(fn ($r) => $r['status']->isViolation())->count(),
                'rated' => in_array($l, Level::compliable(), true),
            ]),
        ])->title('Ringkasan Sistem');
    }
}
