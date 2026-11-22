<?php

use App\Enums\TaskStatus;
use App\Services\DirectionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('cascades an instruction from kadis down to staff and back up with verification', function () {
    $org = makeOrg();
    $service = app(DirectionService::class);
    $p = $org['positions'];

    $general = $service->send($org['kadis'], 'general', 'Arahan umum', [['position_id' => $p['e3']->id]]);
    $inboxE3 = DirectionService::inbox($org['e3'])->first();
    $service->confirm($org['e3'], $inboxE3);

    $toE4 = $service->send($org['e3'], 'task', 'Teruskan', [['position_id' => $p['e4']->id, 'items' => [['title' => 'Siapkan bahan']]]], null, $general->id);
    expect($toE4->parent_id)->toBe($general->id);

    $duty = $p['staff']->duties->first();
    $service->send($org['e4'], 'task', 'Kerjakan', [['position_id' => $p['staff']->id, 'items' => [['source' => 'duty', 'duty_id' => $duty->id, 'title' => ''], ['title' => 'Item manual']]]]);

    $recipient = DirectionService::inbox($org['staff'])->with('tasks')->first();
    expect($recipient->tasks)->toHaveCount(2)
        ->and($recipient->tasks->first()->source)->toBe('duty')
        ->and($recipient->tasks->first()->title)->toBe($duty->title);

    foreach ($recipient->tasks as $task) {
        $service->updateTask($org['staff'], $task, TaskStatus::Selesai->value);
    }
    $service->report($org['staff'], $recipient->fresh('tasks'), 'Beres');
    $service->verify($org['e4'], $recipient->fresh(), 'approved');

    $recipient->refresh();
    expect($recipient->confirmed_at)->not->toBeNull()
        ->and($recipient->reported_at)->not->toBeNull()
        ->and($recipient->verification_status)->toBe('approved')
        ->and($org['staff']->notifications()->count())->toBeGreaterThan(0);
});

it('only allows sending to direct subordinates and requires checklist items below kadis', function () {
    $org = makeOrg();
    $service = app(DirectionService::class);

    expect(fn () => $service->send($org['e3'], 'task', 'x', [['position_id' => $org['positions']['staff']->id, 'items' => [['title' => 'a']]]]))
        ->toThrow(ValidationException::class);

    expect(fn () => $service->send($org['e3'], 'task', 'x', [['position_id' => $org['positions']['e4']->id, 'items' => []]]))
        ->toThrow(ValidationException::class);

    expect(fn () => $service->send($org['staff'], 'task', 'x', []))->toThrow(AuthorizationException::class);
});

it('prevents other users from acting on a recipient', function () {
    $org = makeOrg();
    $service = app(DirectionService::class);
    $service->send($org['e4'], 'task', 'Kerjakan', [['position_id' => $org['positions']['staff']->id, 'items' => [['title' => 'a']]]]);
    $recipient = DirectionService::inbox($org['staff'])->first();

    expect(fn () => $service->confirm($org['e3'], $recipient))->toThrow(AuthorizationException::class);
    expect(fn () => $service->verify($org['e3'], $recipient, 'approved'))->toThrow(AuthorizationException::class);
    expect(DirectionService::canView($org['e3'], $recipient))->toBeTrue(); // atasan dari atasan boleh melihat
});

it('lets the direct superior approve initiatives which then become checklist options', function () {
    $org = makeOrg();
    $service = app(DirectionService::class);
    $initiative = $service->proposeInitiative($org['staff'], 'Rapikan arsip');

    expect(fn () => $service->reviewInitiative($org['e3'], $initiative, true))->toThrow(AuthorizationException::class);
    $service->reviewInitiative($org['e4'], $initiative, true);

    $service->send($org['e4'], 'task', 'Kerjakan', [['position_id' => $org['positions']['staff']->id, 'items' => [['source' => 'initiative', 'initiative_id' => $initiative->id, 'title' => '']]]]);
    expect(DirectionService::inbox($org['staff'])->first()->tasks->first()->source)->toBe('initiative');
});
