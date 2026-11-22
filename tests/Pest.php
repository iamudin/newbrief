<?php

use App\Models\Duty;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Struktur minimal: Kadis → Eselon 3 → Eselon 4 → Staff.
 *
 * @return array{kadis: User, e3: User, e4: User, staff: User, positions: array<string, Position>}
 */
function makeOrg(): array
{
    $make = function (string $name, string $level, ?Position $parent) {
        $position = Position::query()->create(['name' => $name, 'level' => $level, 'parent_id' => $parent?->id]);
        $user = User::factory()->create(['name' => 'Pejabat '.$name]);
        PositionAssignment::query()->create(['position_id' => $position->id, 'user_id' => $user->id, 'started_at' => today()->subMonth()]);
        Duty::query()->create(['position_id' => $position->id, 'title' => 'Tupoksi '.$name]);

        return [$position, $user];
    };

    [$pk, $kadis] = $make('Kepala Dinas', 'kadis', null);
    [$p3, $e3] = $make('Kabid', 'eselon3', $pk);
    [$p4, $e4] = $make('Kasubag', 'eselon4', $p3);
    [$ps, $staff] = $make('Operator', 'staff', $p4);

    return ['kadis' => $kadis, 'e3' => $e3, 'e4' => $e4, 'staff' => $staff, 'positions' => ['kadis' => $pk, 'e3' => $p3, 'e4' => $p4, 'staff' => $ps]];
}
