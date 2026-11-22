<?php

namespace App\Services;

use App\Models\FieldAssignment;
use App\Models\LocationLog;
use App\Models\Setting;
use App\Models\User;

class LocationService
{
    public function distanceToOffice(float $latitude, float $longitude): int
    {
        return (int) round($this->haversine(
            $latitude,
            $longitude,
            (float) Setting::get('office_latitude'),
            (float) Setting::get('office_longitude'),
        ));
    }

    public function zoneFor(User $user, float $latitude, float $longitude): string
    {
        if ($this->distanceToOffice($latitude, $longitude) <= (int) Setting::get('office_radius')) {
            return 'office';
        }

        return $this->isOnFieldDuty($user) ? 'field' : 'outside';
    }

    public function isOnFieldDuty(User $user, ?string $date = null): bool
    {
        return FieldAssignment::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date ?? today()->toDateString())
            ->exists();
    }

    /**
     * Rekam lokasi sekali saat pengguna melakukan aksi.
     *
     * @param  array{latitude?: mixed, longitude?: mixed, accuracy?: mixed}|null  $coords
     */
    public function record(User $user, ?array $coords, string $context): ?LocationLog
    {
        if (! isset($coords['latitude'], $coords['longitude']) || ! is_numeric($coords['latitude']) || ! is_numeric($coords['longitude'])) {
            return null;
        }

        $lat = (float) $coords['latitude'];
        $lng = (float) $coords['longitude'];

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return LocationLog::query()->create([
            'user_id' => $user->id,
            'position_id' => $user->position()?->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => isset($coords['accuracy']) && is_numeric($coords['accuracy']) ? (int) $coords['accuracy'] : null,
            'distance_m' => $this->distanceToOffice($lat, $lng),
            'zone' => $this->zoneFor($user, $lat, $lng),
            'context' => $context,
        ]);
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
