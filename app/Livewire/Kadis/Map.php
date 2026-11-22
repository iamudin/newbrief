<?php

namespace App\Livewire\Kadis;

use App\Enums\Level;
use App\Models\Position;
use App\Models\Setting;
use App\Services\EmployeeOverview;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::app')]
class Map extends Component
{
    #[Url]
    public string $zone = '';

    public function render(EmployeeOverview $overview)
    {
        $positions = Position::query()->with('currentAssignment.user')->where('level', '!=', Level::Kadis->value)->get();
        $rows = $overview->rows($positions);

        $markers = $rows->filter(fn ($r) => $r['location'] && (! $this->zone || $r['location']->zone === $this->zone))
            ->map(fn ($r) => [
                'lat' => (float) $r['location']->latitude,
                'lng' => (float) $r['location']->longitude,
                'zone' => $r['location']->zone,
                'name' => $r['user']->name,
                'position' => $r['position']->name,
                'time' => $r['location']->created_at->format('H.i'),
                'distance' => $r['location']->distance_m,
                'destination' => $r['field']?->destination,
            ])->values();

        return view('livewire.kadis.map', [
            'markers' => $markers,
            'rows' => $rows,
            'counts' => [
                'office' => $rows->where('location.zone', 'office')->count(),
                'field' => $rows->where('location.zone', 'field')->count(),
                'outside' => $rows->where('location.zone', 'outside')->count(),
                'none' => $rows->whereNull('location')->count(),
            ],
            'office' => ['lat' => (float) Setting::get('office_latitude'), 'lng' => (float) Setting::get('office_longitude'), 'radius' => (int) Setting::get('office_radius'), 'name' => Setting::get('office_name')],
        ])->title('Peta');
    }
}
