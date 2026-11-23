<?php

use App\Livewire\Admin\WhatsApp;
use App\Models\Position;
use App\Models\PositionAssignment;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\ArsipService;
use App\Services\DirectionReminderService;
use App\Services\DirectionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Setting::put('wa_enabled', '1');
    Setting::put('wa_host', 'https://wa.test');
    Setting::put('wa_session', 'brief');
    Http::fake(['wa.test/message/send-text' => Http::response(['status' => true])]);

    $this->org = makeOrg();
    foreach (['kadis' => '081200000001', 'e3' => '081200000002', 'e4' => '081200000003', 'staff' => '081200000004'] as $key => $phone) {
        $this->org[$key]->update(['phone' => $phone]);
    }
});

afterEach(fn () => Carbon::setTestNow());

it('sends WhatsApp for new instructions and reprimands', function () {
    $org = $this->org;
    app(DirectionService::class)->send($org['e4'], 'task', 'Kerjakan laporan', [['position_id' => $org['positions']['staff']->id, 'items' => [['title' => 'a']]]]);
    app(DirectionService::class)->reprimand($org['kadis'], $org['positions']['e3'], 'Mohon tepat waktu');

    Http::assertSent(fn ($r) => $r->url() === 'https://wa.test/message/send-text' && $r['to'] === '6281200000004' && $r['session'] === 'brief' && str_contains($r['text'], 'Kerjakan laporan'));
    Http::assertSent(fn ($r) => $r['to'] === '6281200000002' && str_contains($r['text'], 'Teguran'));
    expect(WhatsappMessage::query()->where('status', 'sent')->count())->toBe(2);
});

it('does not send when disabled or the phone number is empty', function () {
    $org = $this->org;
    $org['staff']->update(['phone' => null]);
    app(DirectionService::class)->send($org['e4'], 'task', 'x', [['position_id' => $org['positions']['staff']->id, 'items' => [['title' => 'a']]]]);

    Setting::put('wa_enabled', '0');
    app(DirectionService::class)->reprimand($org['kadis'], $org['positions']['e3'], 'x');

    Http::assertNothingSent();
    expect($org['staff']->notifications()->count())->toBe(1); // notifikasi aplikasi tetap masuk
});

it('notifies kasubag, kadis and disposition recipients of incoming letters', function () {
    Storage::fake('local');
    $org = $this->org;
    $opPos = Position::query()->create(['name' => 'Operator E-Arsip', 'level' => 'arsip', 'parent_id' => $org['positions']['e4']->id]);
    $operator = User::factory()->create();
    PositionAssignment::query()->create(['position_id' => $opPos->id, 'user_id' => $operator->id, 'started_at' => today()->subMonth()]);

    $service = app(ArsipService::class);
    $letter = $service->store($operator, ['letter_number' => '1/X', 'letter_date' => today()->toDateString(), 'received_date' => today()->toDateString(), 'sender' => 'Setda', 'subject' => 'Undangan rapat', 'nature' => 'segera'], UploadedFile::fake()->create('s.pdf', 10, 'application/pdf'));
    Http::assertSent(fn ($r) => $r['to'] === '6281200000003' && str_contains($r['text'], 'perlu paraf'));

    $service->paraf($org['e4'], $letter);
    Http::assertSent(fn ($r) => $r['to'] === '6281200000001' && str_contains($r['text'], 'didisposisi'));

    $service->dispose($org['kadis'], $letter->fresh(), [$org['positions']['e3']->id], ['Selesaikan segera'], 'Hadiri');
    Http::assertSent(fn ($r) => $r['to'] === '6281200000002' && str_contains($r['text'], 'Disposisi surat masuk'));

    $service->forward($org['e3'], $letter->dispositions()->first(), [$org['positions']['e4']->id], 'Siapkan bahan');
    Http::assertSent(fn ($r) => $r['to'] === '6281200000003' && str_contains($r['text'], 'Siapkan bahan'));
});

it('reminds officials who have not sent instructions after their deadline, once per day', function () {
    $org = $this->org;
    Carbon::setTestNow('2026-11-23 08:05'); // Senin, lewat batas Eselon 3 (08:00), belum batas Eselon 4

    expect(app(DirectionReminderService::class)->run())->toBe(1);
    Http::assertSent(fn ($r) => $r['to'] === '6281200000002' && str_contains($r['text'], 'belum membuat instruksi'));

    // Eselon 4 sudah membuat instruksi sebelum batasnya → tidak diingatkan
    app(DirectionService::class)->send($org['e4'], 'task', 'x', [['position_id' => $org['positions']['staff']->id, 'items' => [['title' => 'a']]]]);
    Carbon::setTestNow('2026-11-23 08:31');
    expect(app(DirectionReminderService::class)->run())->toBe(0)
        ->and(app(DirectionReminderService::class)->run())->toBe(0); // Eselon 3 tidak diingatkan dua kali

    // Akhir pekan tidak ada pengingat
    Carbon::setTestNow('2026-11-28 09:00');
    expect(app(DirectionReminderService::class)->run())->toBe(0);
});

it('marks a message failed without breaking the action when the gateway is down', function () {
    Setting::put('wa_host', 'https://down.test');
    Http::fake(['down.test/*' => Http::response('down', 500)]);
    app(DirectionService::class)->reprimand($this->org['kadis'], $this->org['positions']['e3'], 'x');

    expect(WhatsappMessage::query()->first()->status)->toBe('failed');
});

it('pairs via QR without regenerating the QR while waiting for the scan', function () {
    $admin = User::factory()->admin()->create();
    Http::fake([
        'wa.test/session/start' => Http::response(['qr' => '2@abc']),
        'wa.test/session/brief' => Http::sequence()
            ->push(['data' => ['status' => 'disconnected']]) // saat membuka jendela
            ->push(['data' => ['status' => 'connecting']])   // polling 1
            ->push(['data' => ['status' => 'connecting']])   // polling 2
            ->push(['data' => ['status' => 'connected', 'connection' => ['isConnected' => true]]]),
        'wa.test/session/logout' => Http::response(['ok' => true]),
    ]);

    $page = Livewire::actingAs($admin)->test(WhatsApp::class)
        ->call('openPairing')
        ->assertSet('pairingStatus', 'waiting')
        ->assertSet('qr', '2@abc')
        ->call('pollPairing')
        ->call('pollPairing')
        ->assertSet('qr', '2@abc')
        ->assertSet('pairingStatus', 'waiting');

    // Selama menunggu scan: /session/start hanya dipanggil sekali dan tidak pernah logout otomatis
    Http::assertSentCount(4);
    Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/session/logout'));

    $page->call('pollPairing')->assertSet('pairingStatus', 'connected')->assertSee('Perangkat sudah terhubung');
    expect(Setting::get('wa_connection'))->toBe('connected');
});

it('resets the session at most once when the gateway says the session already exists', function () {
    $admin = User::factory()->admin()->create();
    Http::fake([
        'wa.test/session/brief' => Http::response(['data' => ['status' => 'connecting']]),
        'wa.test/session/start' => Http::sequence()
            ->push(['message' => 'Session already exist'], 400)
            ->push(['qr' => '2@fresh'])
            ->push(['message' => 'Session already exist'], 400),
        'wa.test/session/logout' => Http::response(['ok' => true]),
    ]);

    $page = Livewire::actingAs($admin)->test(WhatsApp::class)
        ->call('openPairing')
        ->assertSet('qr', '2@fresh')
        ->assertSet('pairingStatus', 'waiting');

    // Polling berkali-kali tidak memicu logout/start lagi → QR tetap sama
    $page->call('pollPairing')->call('pollPairing')->call('pollPairing')->assertSet('qr', '2@fresh');
    Http::assertSentCount(1 + 1 + 1 + 1 + 1 + 3); // status, start, status, logout, start, 3x status
    expect(collect(Http::recorded())->filter(fn ($p) => str_ends_with($p[0]->url(), '/session/logout'))->count())->toBe(1);
});

it('saves gateway settings from the admin page', function () {
    $admin = User::factory()->admin()->create();
    Http::fake(['*' => Http::response(['data' => ['status' => 'disconnected']])]);

    Livewire::actingAs($admin)->test(WhatsApp::class)
        ->set('host', 'https://gateway.baru')->set('session', 'dinas-pm')->set('enabled', true)
        ->call('save')->assertHasNoErrors();
    expect(Setting::get('wa_host'))->toBe('https://gateway.baru')->and(Setting::get('wa_session'))->toBe('dinas-pm');

    $this->actingAs($admin)->get(route('admin.whatsapp'))->assertOk()->assertSee('Scan WhatsApp Session');
});

it('shows whether the WhatsApp device is connected or needs a scan', function () {
    $admin = User::factory()->admin()->create();

    Http::fake(['wa.test/session/brief' => Http::sequence()
        ->push(['data' => ['status' => 'connecting']])
        ->push(['data' => ['status' => 'connected']])
        ->push('error', 500)]);

    $page = Livewire::actingAs($admin)->test(WhatsApp::class)
        ->call('checkConnection', true)
        ->assertSee('Belum terhubung — perlu scan QR')
        ->assertSee('Scan QR sekarang');
    expect(Setting::get('wa_connection'))->toBe('needs_scan');

    $page->call('checkConnection', true)->assertSee('Terhubung')->assertDontSee('Scan QR sekarang');
    expect(Setting::get('wa_connection'))->toBe('connected');

    // Pemeriksaan otomatis tidak memanggil gateway lagi dalam < 45 detik
    $page->call('checkConnection');
    expect(Setting::get('wa_connection'))->toBe('connected');

    $page->call('checkConnection', true)->assertSee('Gateway tidak dapat dihubungi');

    // Belum dikonfigurasi
    Setting::put('wa_host', '');
    Livewire::actingAs($admin)->test(WhatsApp::class)->assertSee('Belum dikonfigurasi');
});

it('shows the WhatsApp delivery status on letters, dispositions, instructions and reprimands', function () {
    Storage::fake('local');
    $org = $this->org;
    $org['e3']->update(['phone' => null]); // Eselon 3 belum punya nomor → tercatat "tidak dikirim"

    $opPos = Position::query()->create(['name' => 'Operator E-Arsip', 'level' => 'arsip', 'parent_id' => $org['positions']['e4']->id]);
    $operator = User::factory()->create();
    PositionAssignment::query()->create(['position_id' => $opPos->id, 'user_id' => $operator->id, 'started_at' => today()->subMonth()]);

    $arsip = app(ArsipService::class);
    $letter = $arsip->store($operator, ['letter_number' => '9/X', 'letter_date' => today()->toDateString(), 'received_date' => today()->toDateString(), 'sender' => 'Setda', 'subject' => 'Rapat', 'nature' => 'biasa'], UploadedFile::fake()->create('s.pdf', 10, 'application/pdf'));

    expect($letter->whatsappFor('arsip_paraf')->status)->toBe('sent');

    // Operator melihat status WA ke Kasubag di daftar & detail surat
    $this->actingAs($operator)->get(route('arsip.index'))->assertSee('WA terkirim')->assertSee('data-wa-status="sent"', false);
    $this->actingAs($operator)->get(route('arsip.show', $letter))->assertSee('Notifikasi ke pemaraf');

    $arsip->paraf($org['e4'], $letter);
    $arsip->dispose($org['kadis'], $letter->fresh(), [$org['positions']['e3']->id], []);
    $disposition = $letter->dispositions()->first();

    expect($letter->fresh()->whatsappFor('arsip_kadis')->status)->toBe('sent')
        ->and($disposition->whatsappFor()->status)->toBe('skipped')
        ->and($disposition->whatsappFor()->error)->toContain('belum diisi');

    $this->actingAs($org['kadis'])->get(route('arsip.show', $letter))
        ->assertSee('Notifikasi ke Kadis')
        ->assertSee('WA tidak dikirim')
        ->assertSee('belum diisi');

    // Instruksi & teguran
    app(DirectionService::class)->send($org['e4'], 'task', 'Kerjakan', [['position_id' => $org['positions']['staff']->id, 'items' => [['title' => 'a']]]]);
    $this->actingAs($org['e4'])->get(route('eselon4.directions'))->assertSee('data-wa-status="sent"', false);

    $reprimand = app(DirectionService::class)->reprimand($org['kadis'], $org['positions']['staff'], 'Tepat waktu ya');
    expect($reprimand->whatsappFor('teguran')->status)->toBe('sent');
});

it('records skipped messages with a reason when WhatsApp is disabled', function () {
    Setting::put('wa_enabled', '0');
    app(DirectionService::class)->reprimand($this->org['kadis'], $this->org['positions']['e3'], 'x');

    $message = WhatsappMessage::query()->first();
    expect($message->status)->toBe('skipped')->and($message->error)->toContain('dinonaktifkan');
    Http::assertNothingSent();
});
