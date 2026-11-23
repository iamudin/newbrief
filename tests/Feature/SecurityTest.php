<?php

use App\Livewire\Admin\Settings;
use App\Livewire\Admin\Users;
use App\Livewire\Auth\Login;
use App\Livewire\Shared\ChangePassword;
use App\Livewire\Shared\RecipientDetail;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\DirectionService;
use App\Services\WhatsAppService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('sends security headers on every page', function () {
    $this->get('/login')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy');
});

it('rejects executable or polyglot files uploaded as the logo', function () {
    $admin = User::factory()->admin()->create();
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    foreach ([
        UploadedFile::fake()->createWithContent('shell.php', $png.'<?php echo "pwned"; ?>'),
        UploadedFile::fake()->createWithContent('logo.php', '<?php system($_GET["c"]); ?>'),
        UploadedFile::fake()->createWithContent('logo.svg', '<svg onload="alert(1)"></svg>'),
    ] as $file) {
        Livewire::actingAs($admin)->test(Settings::class)
            ->set('logo', $file)
            ->assertHasErrors('logo')
            ->call('save');
    }

    expect((string) Setting::get('agency_logo'))->toBe('')
        ->and(glob(public_path('uploads/*.php')) ?: [])->toBeEmpty();
});

it('stores a valid logo under a random name with an extension derived from its content', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)->test(Settings::class)
        ->set('logo', UploadedFile::fake()->image('Logo Dinas.PNG', 64, 64))
        ->call('save')
        ->assertHasNoErrors();

    $logo = (string) Setting::get('agency_logo');
    expect($logo)->toMatch('/^uploads\/logo-instansi-[a-f0-9]{16}\.png$/');
    @unlink(public_path($logo));
});

it('logs out a deactivated user even with an active session', function () {
    $org = makeOrg();
    $this->actingAs($org['staff'])->get(route('staff.dashboard'))->assertOk();

    $org['staff']->update(['is_active' => false]);

    $this->get(route('staff.dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('forces a password change after an admin reset and enforces a strong password', function () {
    $org = makeOrg();
    $admin = User::factory()->admin()->create();
    Livewire::actingAs($admin)->test(Users::class)->call('resetPassword', $org['staff']->id);

    $staff = $org['staff']->fresh();
    expect($staff->must_change_password)->toBeTrue();

    $staff->forceFill(['password' => 'Sementara123'])->save();
    $this->actingAs($staff)->get(route('staff.dashboard'))->assertRedirect(route('password.change'));

    // Password lemah / NIP ditolak
    Livewire::actingAs($staff)->test(ChangePassword::class)
        ->set('current_password', 'Sementara123')->set('password', 'abcdefgh')->set('password_confirmation', 'abcdefgh')
        ->call('save')->assertHasErrors('password');

    Livewire::actingAs($staff)->test(ChangePassword::class)
        ->set('current_password', 'salah')->set('password', 'Rahasia2026')->set('password_confirmation', 'Rahasia2026')
        ->call('save')->assertHasErrors('current_password');

    Livewire::actingAs($staff)->test(ChangePassword::class)
        ->set('current_password', 'Sementara123')->set('password', 'Rahasia2026')->set('password_confirmation', 'Rahasia2026')
        ->call('save')->assertHasNoErrors()->assertRedirect(route('staff.dashboard'));

    expect($staff->fresh()->must_change_password)->toBeFalse();
});

it('throttles login attempts per account and per IP', function () {
    User::factory()->create(['nip' => '111']);

    foreach (range(1, 5) as $i) {
        Livewire::test(Login::class)->set('nip', '111')->set('password', 'salah')->call('login');
    }

    Livewire::test(Login::class)->set('nip', '111')->set('password', 'password')->call('login')
        ->assertHasErrors('nip')->assertSee('Terlalu banyak percobaan');
});

it('hides demo accounts on the login page unless explicitly enabled', function () {
    User::factory()->create(['nip' => '999888777']);
    config(['app.demo_accounts' => false]);

    $this->get('/login')->assertDontSee('999888777')->assertDontSee('Akun demo');
});

it('ignores external referers for the back button', function () {
    $org = makeOrg();
    app(DirectionService::class)->send($org['e4'], 'task', 'x', [['position_id' => $org['positions']['staff']->id, 'items' => [['title' => 'a']]]]);
    $recipient = DirectionService::inbox($org['staff'])->first();

    $this->actingAs($org['staff'])
        ->withHeader('Referer', 'https://phishing.example.com/login')
        ->get(route('recipient.show', $recipient))
        ->assertOk()
        ->assertDontSee('phishing.example.com');
});

it('blocks tampering with locked Livewire properties', function () {
    $org = makeOrg();
    app(DirectionService::class)->send($org['e4'], 'task', 'x', [['position_id' => $org['positions']['staff']->id, 'items' => [['title' => 'a']]]]);
    $recipient = DirectionService::inbox($org['staff'])->first();

    expect(fn () => Livewire::actingAs($org['staff'])->test(RecipientDetail::class, ['recipient' => $recipient])->set('recipient', 999))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('rejects disallowed file types on task attachments', function () {
    $org = makeOrg();
    app(DirectionService::class)->send($org['e4'], 'task', 'x', [['position_id' => $org['positions']['staff']->id, 'items' => [['title' => 'a']]]]);
    $recipient = DirectionService::inbox($org['staff'])->first();
    $task = $recipient->tasks->first();

    Livewire::actingAs($org['staff'])->test(RecipientDetail::class, ['recipient' => $recipient])
        ->set("taskFiles.{$task->id}", UploadedFile::fake()->createWithContent('xss.html', '<script>alert(1)</script>'))
        ->assertHasErrors();

    expect($task->fresh()->file_path)->toBeNull();
});

it('allows only one reprimand per employee per day', function () {
    $org = makeOrg();
    app(DirectionService::class)->reprimand($org['kadis'], $org['positions']['staff'], 'Pertama');

    expect(fn () => app(DirectionService::class)->reprimand($org['kadis'], $org['positions']['staff'], 'Kedua'))
        ->toThrow(ValidationException::class);
});

it('caps WhatsApp messages per recipient per hour', function () {
    Setting::put('wa_enabled', '1');
    Setting::put('wa_host', 'https://wa.test');
    Setting::put('wa_session', 'brief');
    Http::fake(['wa.test/*' => Http::response(['ok' => true])]);
    $user = User::factory()->create(['phone' => '081234567890']);
    $service = app(WhatsAppService::class);

    foreach (range(1, WhatsAppService::HOURLY_LIMIT + 3) as $i) {
        $service->queueToUser($user, "pesan {$i}");
    }

    expect(WhatsappMessage::query()->where('status', 'sent')->count())->toBe(WhatsAppService::HOURLY_LIMIT)
        ->and(WhatsappMessage::query()->where('status', 'skipped')->first()->error)->toContain('anti-spam');
});

it('verifies the gateway TLS certificate by default', function () {
    Setting::put('wa_host', 'https://wa.test');
    Http::fake(['wa.test/*' => Http::response(['data' => ['status' => 'connected']])]);

    app(WhatsAppService::class)->sessionStatus();

    Http::assertSent(fn ($request) => true);
    expect(Setting::get('wa_verify_tls'))->toBe('1');
});

it('denies access to letters and files of other people', function () {
    $org = makeOrg();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('file', ['surat', 1]))->assertNotFound();
    $this->actingAs($org['staff'])->get('/admin/user')->assertForbidden();
    $this->actingAs($org['staff'])->get(route('admin.whatsapp'))->assertForbidden();
});
