<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @var array<string, string> */
    public const DEFAULTS = [
        'agency_name' => 'Dinas Penanaman Modal dan PTSP',
        'government_name' => 'Pemerintah Kabupaten Bengkalis',
        'regency' => 'Bengkalis',
        'agency_logo' => '',
        'deadline_eselon3' => '08:00',
        'deadline_eselon4' => '08:30',
        'deadline_staff' => '08:45',
        'workday_start' => '07:30',
        'report_deadline' => '14:30',
        'verify_deadline' => '15:00',
        'workday_end' => '16:00',
        'office_name' => 'Kantor Dinas',
        'office_latitude' => '-6.1753924',
        'office_longitude' => '106.8271528',
        'office_radius' => '150',
        'wa_enabled' => '0',
        'wa_host' => '',
        'wa_session' => 'brief',
        'wa_remind_directions' => '1',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = Cache::rememberForever('settings.all', fn () => self::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, ?string $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }
}
