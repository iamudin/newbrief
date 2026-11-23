<?php

namespace App\Services;

use App\Enums\Level;
use App\Enums\TaskStatus;
use App\Models\FieldAssignment;
use App\Models\LocationLog;
use App\Models\Position;
use App\Models\Reprimand;
use App\Models\Task;
use Illuminate\Support\Collection;

/** Ringkasan per pegawai untuk hari ini: tugas, kepatuhan, lokasi terakhir. */
class EmployeeOverview
{
    public function __construct(private ComplianceService $compliance) {}

    /**
     * @param  Collection<int, Position>  $positions
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(Collection $positions): Collection
    {
        $positions = $positions->filter(fn (Position $p) => $p->level !== Level::Kadis)->values();
        $ids = $positions->pluck('id');
        $today = today()->toDateString();

        $compliance = $this->compliance->forDate($today, $ids);

        $tasks = Task::query()
            ->join('direction_recipients as r', 'r.id', '=', 'tasks.direction_recipient_id')
            ->join('directions as d', 'd.id', '=', 'r.direction_id')
            ->whereIn('r.to_position_id', $ids)
            ->whereDate('d.date', $today)
            ->selectRaw('r.to_position_id as pid, r.to_user_id as uid, COUNT(*) as total, SUM(tasks.status = ?) as done', [TaskStatus::Selesai->value])
            ->groupBy('r.to_position_id', 'r.to_user_id')
            ->get();

        $holderIds = $positions->map(fn (Position $p) => $p->holder()?->id)->filter();

        $locations = LocationLog::query()
            ->whereIn('user_id', $holderIds)
            ->whereDate('created_at', $today)
            ->latest('id')
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');

        $field = FieldAssignment::query()->whereIn('user_id', $holderIds)->whereDate('date', $today)->get()->keyBy('user_id');
        $reprimands = Reprimand::query()->whereIn('to_user_id', $holderIds)->whereDate('date', $today)->with('whatsappMessages')->latest('id')->get()->groupBy('to_user_id');

        return $positions->map(function (Position $position) use ($compliance, $tasks, $locations, $field, $reprimands) {
            $holder = $position->holder();
            $taskRow = $tasks->first(fn ($t) => $t->pid === $position->id && ($position->level !== Level::Staff || $t->uid === $holder?->id));
            $total = (int) ($taskRow->total ?? 0);
            $done = (int) ($taskRow->done ?? 0);

            return [
                'position' => $position,
                'user' => $holder,
                'tasks_total' => $total,
                'tasks_done' => $done,
                'tasks_percent' => $total ? (int) round($done / $total * 100) : 0,
                'compliance' => $compliance->get($position->id),
                'location' => $holder ? $locations->get($holder->id) : null,
                'field' => $holder ? $field->get($holder->id) : null,
                'reprimanded' => $holder ? $reprimands->has($holder->id) : false,
                'reprimand' => $holder ? $reprimands->get($holder->id)?->first() : null,
            ];
        });
    }
}
