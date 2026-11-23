<?php

namespace App\Livewire\Shared;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Ganti password sendiri; wajib bila password masih sementara (dibuat / direset admin). */
#[Layout('layouts::guest')]
class ChangePassword extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function save()
    {
        $user = Auth::user();
        $key = 'change-password:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['current_password' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.']);
        }

        $this->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::defaults(), 'different:current_password'],
        ], ['password.different' => 'Password baru harus berbeda dari password lama.'], ['current_password' => 'password lama', 'password' => 'password baru']);

        if (! Hash::check($this->current_password, $user->password)) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages(['current_password' => 'Password lama tidak sesuai.']);
        }

        if (in_array(mb_strtolower($this->password), [mb_strtolower($user->nip), 'password', 'password1', '12345678'], true)) {
            throw ValidationException::withMessages(['password' => 'Password terlalu mudah ditebak (jangan memakai NIP atau kata umum).']);
        }

        RateLimiter::clear($key);
        $user->forceFill([
            'password' => $this->password,
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        // Keluarkan sesi di perangkat lain, perbarui sesi saat ini.
        Auth::logoutOtherDevices($this->password);
        session()->regenerate();
        session()->flash('toast', 'Password berhasil diganti.');

        return $this->redirect($user->homeRoute());
    }

    public function render()
    {
        return view('livewire.shared.change-password', ['forced' => Auth::user()->must_change_password])->title('Ganti Password');
    }
}
