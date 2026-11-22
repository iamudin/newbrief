<?php

use App\Livewire\Shared\RecipientDetail;
use App\Livewire\Staff\Tasks;
use App\Models\User;
use App\Services\DirectionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('shows the sender of a report its own attachments and lets them download it', function () {
    Storage::fake('local');
    $org = makeOrg();
    $service = app(DirectionService::class);
    $service->send($org['e4'], 'task', 'Kerjakan', [['position_id' => $org['positions']['staff']->id, 'items' => [['title' => 'Foto kegiatan']]]]);
    $recipient = DirectionService::inbox($org['staff'])->with('tasks')->first();

    $task = $recipient->tasks->first();
    $service->attachTaskFile($org['staff'], $task, UploadedFile::fake()->image('bukti-tugas.jpg'));
    $service->updateTask($org['staff'], $task->fresh(), 'selesai');

    Livewire::actingAs($org['staff'])->test(RecipientDetail::class, ['recipient' => $recipient])
        ->set('reportNote', 'Sudah selesai')
        ->set('reportFile', UploadedFile::fake()->create('laporan-harian.pdf', 100, 'application/pdf'))
        ->call('sendReport', null)
        ->assertSee('Laporan yang Anda kirim')
        ->assertSee('laporan-harian.pdf')
        ->assertSee('bukti-tugas.jpg')
        ->assertSee('Menunggu verifikasi atasan');

    $recipient->refresh();
    $this->actingAs($org['staff'])->get(route('file', ['laporan', $recipient->id]))->assertOk();
    $this->actingAs($org['staff'])->get(route('file', ['tugas', $task->id]))->assertOk();
    $this->actingAs($org['staff'])->get(route('staff.tasks'))->assertSee('laporan-harian.pdf')->assertSee('bukti-tugas.jpg');

    // Kirim ulang tanpa file baru tetap mempertahankan lampiran sebelumnya
    Livewire::actingAs($org['staff'])->test(RecipientDetail::class, ['recipient' => $recipient])
        ->call('sendReport', null)
        ->assertSee('laporan-harian.pdf');

    // Atasan tetap bisa melihat, pegawai lain yang tidak berhak tidak bisa
    $this->actingAs($org['e4'])->get(route('file', ['laporan', $recipient->id]))->assertOk();
    $other = User::factory()->create();
    $this->actingAs($other)->get(route('file', ['laporan', $recipient->id]))->assertForbidden();
});

it('saves a task attachment as soon as it is uploaded and shows it in the subordinate lists', function () {
    Storage::fake('local');
    $org = makeOrg();
    $service = app(DirectionService::class);

    // Kadis -> Eselon 3: laporan dengan lampiran tampil di riwayat dashboard & halaman Arahan Eselon 3
    $service->send($org['kadis'], 'general', 'Arahan umum', [['position_id' => $org['positions']['e3']->id]]);
    $toE3 = DirectionService::inbox($org['e3'])->first();
    Livewire::actingAs($org['e3'])->test(RecipientDetail::class, ['recipient' => $toE3])
        ->set('reportFile', UploadedFile::fake()->image('laporan-sekretaris.png'))
        ->call('sendReport', null);
    $this->actingAs($org['e3'])->get(route('eselon3.dashboard'))->assertSee('laporan-sekretaris.png');
    $this->actingAs($org['e3'])->get(route('eselon3.directions'))->assertSee('laporan-sekretaris.png');

    // Eselon 4 -> Staff: upload di halaman Tugas langsung tersimpan tanpa menekan tombol status
    $service->send($org['e4'], 'task', 'Kerjakan', [['position_id' => $org['positions']['staff']->id, 'items' => [['title' => 'Foto']]]]);
    $task = DirectionService::inbox($org['staff'])->first()->tasks->first();
    Livewire::actingAs($org['staff'])->test(Tasks::class)
        ->set("taskFiles.{$task->id}", UploadedFile::fake()->image('foto-bukti.jpg'))
        ->assertHasNoErrors()
        ->assertSee('foto-bukti.jpg');

    expect($task->fresh()->file_name)->toBe('foto-bukti.jpg')
        ->and($task->fresh()->status->value)->toBe('pending');
    Storage::disk('local')->assertExists($task->fresh()->file_path);

    $this->actingAs($org['staff'])->get(route('staff.dashboard'))->assertSee('foto-bukti.jpg');
    $this->actingAs($org['e4'])->get(route('eselon4.directions'))->assertSee('foto-bukti.jpg');

    // Upload di halaman detail juga langsung tersimpan; file lama diganti
    $old = $task->fresh()->file_path;
    Livewire::actingAs($org['staff'])->test(RecipientDetail::class, ['recipient' => $task->recipient])
        ->set("taskFiles.{$task->id}", UploadedFile::fake()->create('revisi.pdf', 50, 'application/pdf'))
        ->assertHasNoErrors();
    expect($task->fresh()->file_name)->toBe('revisi.pdf');
    Storage::disk('local')->assertMissing($old);
});
