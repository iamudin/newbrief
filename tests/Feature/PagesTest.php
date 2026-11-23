<?php

use App\Livewire\Auth\Login;
use App\Models\DirectionRecipient;
use App\Models\Letter;
use App\Models\User;

it('renders every page for each role with seeded data', function () {
    $this->seed();
    $byLevel = fn (string $level) => User::query()->where('is_active', true)->get()->first(fn (User $u) => $u->level()?->value === $level);

    $pages = [
        [User::query()->where('is_admin', true)->first(), ['/admin', '/admin/user', '/admin/pegawai', '/admin/struktur', '/admin/tupoksi', '/admin/pengaturan']],
        [$byLevel('kadis'), ['/kadis', '/kadis/arahan', '/kadis/patuh', '/kadis/pegawai', '/kadis/peta', '/jadwal', '/notifikasi', '/instruksi/'.DirectionRecipient::query()->first()->id]],
        [$byLevel('eselon3'), ['/eselon3', '/eselon3/arahan', '/anggota', '/jadwal']],
        [$byLevel('eselon4'), ['/eselon4', '/eselon4/bagi-verifikasi', '/anggota']],
        [$byLevel('staff'), ['/staff', '/staff/tugas', '/staff/tupoksi', '/notifikasi', '/e-arsip']],
        [$byLevel('arsip'), ['/e-arsip', '/e-arsip/surat-baru', '/e-arsip/'.Letter::query()->first()->id]],
        [$byLevel('kadis'), ['/e-arsip', '/e-arsip/'.Letter::query()->whereNotNull('disposed_at')->first()->id, '/e-arsip/'.Letter::query()->whereNotNull('disposed_at')->first()->id.'/lembar-disposisi']],
    ];

    foreach ($pages as [$user, $urls]) {
        foreach ($urls as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }
});

it('redirects home to the dashboard of the user level', function () {
    $org = makeOrg();

    $this->actingAs($org['staff'])->get('/')->assertRedirect(route('staff.dashboard'));
    $this->actingAs($org['kadis'])->get('/')->assertRedirect(route('kadis.dashboard'));
});

it('blocks menus of other levels', function () {
    $org = makeOrg();

    $this->actingAs($org['staff'])->get('/kadis')->assertForbidden();
    $this->actingAs($org['e4'])->get('/eselon3/arahan')->assertForbidden();
    $this->actingAs($org['kadis'])->get('/admin')->assertForbidden();

    auth()->logout();
    $this->get('/kadis')->assertRedirect('/login');
});

it('logs in with NIP and rejects inactive accounts', function () {
    $user = User::factory()->create(['nip' => '123', 'is_admin' => true]);

    Livewire\Livewire::test(Login::class)
        ->set('nip', '123')->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('admin.dashboard'));

    auth()->logout();
    $user->update(['is_active' => false]);

    Livewire\Livewire::test(Login::class)
        ->set('nip', '123')->set('password', 'password')
        ->call('login')
        ->assertHasErrors('nip');
});
