<?php

namespace App\Services;

use App\Enums\Compliance;
use App\Enums\Level;
use App\Models\ComplianceRecord;
use App\Models\DailyCheckin;
use App\Models\Direction;
use App\Models\DirectionRecipient;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aturan kepatuhan:
 *  - Eselon 3 / Eselon 4 / Staff dinilai patuh bila "aksi pertama" hari itu
 *    terjadi paling lambat batas jam levelnya.
 *  - Aksi = konfirmasi SIAP atas arahan atasan, membuat/meneruskan arahan ke bawahan,
 *    atau tap SIAP harian (dengan rekam lokasi).
 *  - Hari libur & akhir pekan tidak dinilai. Kepala Dinas tidak dinilai.
 */
class ComplianceService
{
    public function __construct(private WorkCalendar $calendar) {}

    public static function deadline(Level $level): ?string
    {
        return $level->deadlineKey() ? Setting::get($level->deadlineKey()) : null;
    }

    /**
     * Hasil kepatuhan satu tanggal untuk jabatan yang terisi.
     *
     * @return Collection<int, array{position_id:int, user_id:int|null, level:Level, status:Compliance, acted_at:Carbon|null, deadline:string}>
     */
    public function forDate(Carbon|string $date, ?Collection $positionIds = null): Collection
    {
        $date = Carbon::parse($date)->startOfDay();
        $dateString = $date->toDateString();

        if ($date->isAfter(today())) {
            return collect();
        }

        if ($date->lt(today())) {
            return $this->snapshot($date, $positionIds);
        }

        return $this->evaluate($date, $positionIds);
    }

    /**
     * Ringkasan untuk rentang tanggal.
     *
     * @return array{patuh:int, terlambat:int, tidak:int, menunggu:int, total:int, rows:Collection}
     */
    public function summary(Carbon $from, Carbon $to, ?Collection $positionIds = null): array
    {
        $rows = collect($this->calendar->workdays($from, min($to, today())))
            ->flatMap(fn (string $d) => $this->forDate($d, $positionIds));

        $count = fn (Compliance $c) => $rows->where('status', $c)->count();

        return [
            'patuh' => $count(Compliance::Patuh),
            'terlambat' => $count(Compliance::Terlambat),
            'tidak' => $count(Compliance::Tidak),
            'menunggu' => $count(Compliance::Menunggu),
            'total' => $rows->count(),
            'rows' => $rows,
        ];
    }

    /** Hitung langsung dari data mentah. */
    public function evaluate(Carbon $date, ?Collection $positionIds = null): Collection
    {
        $dateString = $date->toDateString();

        if (! $this->calendar->isWorkday($date)) {
            return collect();
        }

        $holders = $this->holdersOn($dateString, $positionIds);

        if ($holders->isEmpty()) {
            return collect();
        }

        $ids = $holders->keys();

        $confirmed = DirectionRecipient::query()
            ->whereIn('to_position_id', $ids)
            ->whereDate('confirmed_at', $dateString)
            ->selectRaw('to_position_id as pid, MIN(confirmed_at) as acted')
            ->groupBy('to_position_id')
            ->pluck('acted', 'pid');

        $sent = Direction::query()
            ->whereIn('from_position_id', $ids)
            ->whereDate('date', $dateString)
            ->selectRaw('from_position_id as pid, MIN(created_at) as acted')
            ->groupBy('from_position_id')
            ->pluck('acted', 'pid');

        $checkins = DailyCheckin::query()
            ->whereIn('position_id', $ids)
            ->whereDate('date', $dateString)
            ->selectRaw('position_id as pid, MIN(checked_in_at) as acted')
            ->groupBy('position_id')
            ->pluck('acted', 'pid');

        return $holders->map(function (array $holder, int $positionId) use ($confirmed, $sent, $checkins, $date) {
            $actedAt = collect([$confirmed[$positionId] ?? null, $sent[$positionId] ?? null, $checkins[$positionId] ?? null])
                ->filter()
                ->map(fn ($v) => Carbon::parse($v))
                ->sort()
                ->first();

            $deadline = self::deadline($holder['level']);
            $deadlineAt = $date->copy()->setTimeFromTimeString($deadline);

            $status = match (true) {
                $actedAt !== null && $actedAt->lte($deadlineAt) => Compliance::Patuh,
                $actedAt !== null => Compliance::Terlambat,
                $date->isToday() && now()->lt($deadlineAt) => Compliance::Menunggu,
                default => Compliance::Tidak,
            };

            return [
                'position_id' => $positionId,
                'user_id' => $holder['user_id'],
                'level' => $holder['level'],
                'status' => $status,
                'acted_at' => $actedAt,
                'deadline' => $deadline,
            ];
        });
    }

    /** Kunci hasil hari yang sudah lewat ke tabel compliance_records. */
    public function snapshot(Carbon $date, ?Collection $positionIds = null): Collection
    {
        $dateString = $date->toDateString();

        if (! $this->calendar->isWorkday($date)) {
            return collect();
        }

        $existing = ComplianceRecord::query()->whereDate('date', $dateString)->get()->keyBy('position_id');

        if ($existing->isEmpty()) {
            foreach ($this->evaluate($date) as $row) {
                $existing[$row['position_id']] = ComplianceRecord::query()->create([
                    'date' => $dateString,
                    'position_id' => $row['position_id'],
                    'user_id' => $row['user_id'],
                    'level' => $row['level']->value,
                    'status' => $row['status']->value,
                    'deadline' => $row['deadline'],
                    'acted_at' => $row['acted_at'],
                ]);
            }
        }

        return $existing
            ->when($positionIds, fn (Collection $c) => $c->whereIn('position_id', $positionIds->all()))
            ->map(fn (ComplianceRecord $r) => [
                'position_id' => $r->position_id,
                'user_id' => $r->user_id,
                'level' => Level::from($r->level),
                'status' => $r->status,
                'acted_at' => $r->acted_at,
                'deadline' => $r->deadline,
            ])
            ->keyBy('position_id');
    }

    /** @return Collection<int, array{user_id:int, level:Level}> */
    private function holdersOn(string $date, ?Collection $positionIds): Collection
    {
        $levels = collect(Level::compliable())->map->value->all();

        return PositionAssignment::query()
            ->with('position:id,level')
            ->whereDate('started_at', '<=', $date)
            ->where(fn ($q) => $q->whereNull('ended_at')->orWhereDate('ended_at', '>=', $date))
            ->whereHas('position', fn ($q) => $q->whereIn('level', $levels))
            ->when($positionIds, fn ($q) => $q->whereIn('position_id', $positionIds->all()))
            ->orderBy('started_at')
            ->get()
            ->mapWithKeys(fn (PositionAssignment $a) => [$a->position_id => ['user_id' => $a->user_id, 'level' => $a->position->level]]);
    }

    /** Status kepatuhan satu jabatan hari ini. */
    public function today(Position $position): ?array
    {
        return $this->forDate(today(), collect([$position->id]))->get($position->id);
    }
}
