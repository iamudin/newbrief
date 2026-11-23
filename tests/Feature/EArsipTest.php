<?php

use App\Livewire\Arsip\Form;
use App\Livewire\Arsip\Show;
use App\Models\Letter;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\User;
use App\Services\ArsipService;
use App\Services\AssignmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->org = makeOrg();
    $position = Position::query()->create(['name' => 'Operator E-Arsip', 'level' => 'arsip', 'parent_id' => $this->org['positions']['e4']->id]);
    $this->operator = User::factory()->create(['name' => 'Operator']);
    PositionAssignment::query()->create(['position_id' => $position->id, 'user_id' => $this->operator->id, 'started_at' => today()->subMonth()]);
});

function inputLetter(User $operator): Letter
{
    Livewire::actingAs($operator)->test(Form::class)
        ->set('sender', 'Sekretariat Daerah')
        ->set('letter_number', '800/123')
        ->set('letter_date', today()->subDay()->toDateString())
        ->set('received_date', today()->toDateString())
        ->set('subject', 'Undangan rapat')
        ->set('nature', 'segera')
        ->set('file', UploadedFile::fake()->create('surat.pdf', 200, 'application/pdf'))
        ->call('save')
        ->assertHasNoErrors();

    return Letter::query()->latest('id')->first();
}

it('runs the full incoming letter disposition flow', function () {
    $org = $this->org;
    $letter = inputLetter($this->operator);

    expect($letter->agenda_number)->toBe('001/SM/'.now()->year)
        ->and($letter->verifier_position_id)->toBe($org['positions']['e4']->id)
        ->and($letter->stage())->toBe('paraf');
    Storage::disk('local')->assertExists($letter->file_path);

    // Kadis belum bisa disposisi sebelum diparaf
    expect(fn () => app(ArsipService::class)->dispose($org['kadis'], $letter, [$org['positions']['e3']->id], []))
        ->toThrow(AuthorizationException::class);

    // Paraf oleh atasan operator (Kasubag)
    Livewire::actingAs($org['e4'])->test(Show::class, ['letter' => $letter])
        ->set('parafNote', 'Mohon arahan')
        ->call('paraf')
        ->assertHasNoErrors();
    expect($letter->fresh()->stage())->toBe('kadis');

    // Disposisi Kepala Dinas ke Eselon 3 dengan harapan
    Livewire::actingAs($org['kadis'])->test(Show::class, ['letter' => $letter->fresh()])
        ->set("dispositionTargets.{$org['positions']['e3']->id}", true)
        ->set('expectations.0', true) // Tindak lanjuti sesuai ketentuan/prosedur
        ->set('expectations.4', true) // Koordinasikan dengan …
        ->set('expectationCustom.4', 'Bappeda')
        ->set('dispositionNote', 'Segera')
        ->call('dispose')
        ->assertHasNoErrors();

    $letter->refresh();
    expect($letter->stage())->toBe('disposed')
        ->and($letter->expectations)->toBe(['Tindak lanjuti sesuai ketentuan/prosedur', 'Koordinasikan dengan Bappeda'])
        ->and($letter->dispositions)->toHaveCount(1);

    // Eselon 3 membuka (dibaca), membalas, meneruskan ke Eselon 4
    Livewire::actingAs($org['e3'])->test(Show::class, ['letter' => $letter])
        ->set('reply', 'Siap ditindaklanjuti')->call('sendReply')
        ->set("forwardTargets.{$org['positions']['e4']->id}", true)->set('forwardNote', 'Siapkan bahan')->call('forward')
        ->assertHasNoErrors();

    $first = $letter->dispositions()->first();
    expect($first->read_at)->not->toBeNull()
        ->and($first->reply)->toBe('Siap ditindaklanjuti')
        ->and($first->forwarded_at)->not->toBeNull()
        ->and($first->children)->toHaveCount(1);

    // Kadis tidak bisa lagi memperbarui disposisi setelah diteruskan
    expect(fn () => app(ArsipService::class)->resetDisposition($org['kadis'], $letter))->toThrow(ValidationException::class);

    // Eselon 4 meneruskan ke staff, staff menandai selesai
    Livewire::actingAs($org['e4'])->test(Show::class, ['letter' => $letter])
        ->set("forwardTargets.{$org['positions']['staff']->id}", true)->call('forward')->assertHasNoErrors();
    Livewire::actingAs($org['staff'])->test(Show::class, ['letter' => $letter])
        ->call('complete', true);

    expect(ArsipService::inbox($org['staff'])->first()->completed_at)->not->toBeNull()
        ->and($org['e4']->notifications()->count())->toBeGreaterThan(0);

    // Lembar disposisi & file surat bisa dibuka pihak terkait
    $this->actingAs($org['staff'])->get(route('arsip.print', $letter))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($org['e3'])->get(route('file', ['surat', $letter->id]))->assertOk();
});

it('restricts access and editing to the right roles', function () {
    $org = $this->org;
    $letter = inputLetter($this->operator);
    $outsider = User::factory()->create();

    // Staff belum menerima disposisi → tidak bisa melihat
    $this->actingAs($org['staff'])->get(route('arsip.show', $letter))->assertForbidden();
    $this->actingAs($outsider)->get(route('file', ['surat', $letter->id]))->assertForbidden();
    $this->actingAs($org['staff'])->get(route('arsip.create'))->assertForbidden();

    // Operator bisa ubah sebelum paraf, tidak setelahnya
    $this->actingAs($this->operator)->get(route('arsip.edit', $letter))->assertOk();
    app(ArsipService::class)->paraf($org['e4'], $letter);
    $this->actingAs($this->operator)->get(route('arsip.edit', $letter->fresh()))->assertForbidden();

    // Eselon 3 lain / penerima tidak sah tidak bisa membalas disposisi orang lain
    app(ArsipService::class)->dispose($org['kadis'], $letter->fresh(), [$org['positions']['e3']->id], []);
    expect(fn () => app(ArsipService::class)->reply($org['e4'], $letter->dispositions()->first(), 'x'))->toThrow(AuthorizationException::class);
});

it('keeps dispositions on the position for a new eselon holder and excludes the operator from instructions', function () {
    $org = $this->org;
    $letter = inputLetter($this->operator);
    app(ArsipService::class)->paraf($org['e4'], $letter);
    app(ArsipService::class)->dispose($org['kadis'], $letter->fresh(), [$org['positions']['e3']->id], []);

    $this->travel(1)->days();
    $newE3 = User::factory()->create();
    app(AssignmentService::class)->assign($newE3, $org['positions']['e3']->fresh());

    expect(ArsipService::inbox($newE3->fresh())->count())->toBe(1)
        ->and(ArsipService::pendingCount($newE3->fresh()))->toBe(1);
    $this->actingAs($newE3->fresh())->get(route('arsip.index'))->assertSee('Sekretariat Daerah');

    // Operator E-Arsip bukan penerima instruksi Eselon 4
    expect($org['positions']['e4']->subordinates()->pluck('name')->all())->toBe(['Operator']);
});

it('shows the operator menu and agenda list', function () {
    inputLetter($this->operator);

    $this->actingAs($this->operator)->get('/')->assertRedirect(route('arsip.index'));
    $this->actingAs($this->operator)->get(route('arsip.index'))->assertOk()->assertSee('001/SM/'.now()->year)->assertSee('Input Surat');
    $this->actingAs($this->org['e4'])->get(route('arsip.index'))->assertSee('Perlu paraf');
});
