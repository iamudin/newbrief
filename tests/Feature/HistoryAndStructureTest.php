<?php

use App\Livewire\Admin\Structure;
use App\Models\Position;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\DirectionService;
use Livewire\Livewire;

it('keeps task history on the position for new eselon holders but not for new staff', function () {
    $org = makeOrg();
    $service = app(DirectionService::class);
    $p = $org['positions'];

    $this->travel(-2)->days();
    $service->send($org['e3'], 'task', 'Lama', [['position_id' => $p['e4']->id, 'items' => [['title' => 'a']]]]);
    $service->send($org['e4'], 'task', 'Lama', [['position_id' => $p['staff']->id, 'items' => [['title' => 'b']]]]);
    $this->travelBack();

    $newE4 = User::factory()->create();
    $newStaff = User::factory()->create();
    app(AssignmentService::class)->assign($newE4, $p['e4']->fresh());
    app(AssignmentService::class)->assign($newStaff, $p['staff']->fresh());

    expect(DirectionService::inbox($newE4->fresh())->count())->toBe(1)
        ->and(DirectionService::outbox($newE4->fresh())->count())->toBe(1)
        ->and(DirectionService::inbox($newStaff->fresh())->count())->toBe(0)
        ->and($org['e4']->fresh()->position())->toBeNull()
        ->and($p['e4']->holderOn(today()->subDays(2))->id)->toBe($org['e4']->id);
});

it('rejects drag and drop that breaks the level hierarchy', function () {
    $org = makeOrg();
    $p = $org['positions'];
    $admin = User::factory()->admin()->create();

    // Staff dipindah langsung ke bawah Kepala Dinas → ditolak
    Livewire::actingAs($admin)->test(Structure::class)
        ->call('saveTree', [['id' => $p['kadis']->id, 'children' => [['id' => $p['e3']->id, 'children' => [['id' => $p['e4']->id]]], ['id' => $p['staff']->id]]]]);
    expect($p['staff']->fresh()->parent_id)->toBe($p['e4']->id);

    // Pindah sah: Eselon 4 tetap di bawah Eselon 3 baru
    $e3b = Position::query()->create(['name' => 'Kabid 2', 'level' => 'eselon3', 'parent_id' => $p['kadis']->id]);
    Livewire::actingAs($admin)->test(Structure::class)
        ->call('saveTree', [['id' => $p['kadis']->id, 'children' => [['id' => $p['e3']->id], ['id' => $e3b->id, 'children' => [['id' => $p['e4']->id, 'children' => [['id' => $p['staff']->id]]]]]]]]);
    expect($p['e4']->fresh()->parent_id)->toBe($e3b->id);
});
