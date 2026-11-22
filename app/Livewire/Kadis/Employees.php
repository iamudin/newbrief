<?php

namespace App\Livewire\Kadis;

use App\Enums\Level;
use App\Models\Position;
use App\Services\DirectionService;
use App\Services\EmployeeOverview;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::app')]
class Employees extends Component
{
    #[Url]
    public string $level = '';

    #[Url]
    public string $search = '';

    public ?int $expanded = null;

    public function toggle(int $positionId): void
    {
        $this->expanded = $this->expanded === $positionId ? null : $positionId;
    }

    public function render(EmployeeOverview $overview)
    {
        $positions = Position::query()
            ->with('currentAssignment.user', 'parent')
            ->where('level', '!=', Level::Kadis->value)
            ->when($this->level, fn ($q) => $q->where('level', $this->level))
            ->get()
            ->sortBy(fn (Position $p) => [$p->level->rank(), $p->parent_id, $p->sort_order]);

        $rows = $overview->rows($positions)
            ->when($this->search, fn ($c) => $c->filter(fn ($r) => str_contains(mb_strtolower(($r['user']?->name ?? '').' '.$r['position']->name), mb_strtolower($this->search))));

        $detail = null;
        if ($this->expanded && ($row = $rows->firstWhere('position.id', $this->expanded)) && $row['user']) {
            $detail = DirectionService::inbox($row['user'])
                ->whereHas('direction', fn ($q) => $q->whereDate('date', today()))
                ->with('direction.fromUser', 'tasks')
                ->get();
        }

        return view('livewire.kadis.employees', ['rows' => $rows, 'detail' => $detail])->title('Pegawai');
    }
}
