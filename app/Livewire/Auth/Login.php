<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts::guest')]
class Login extends Component
{
    #[Validate('required|string', as: 'NIP')]
    public string $nip = '';

    #[Validate('required|string', as: 'password')]
    public string $password = '';

    public bool $remember = true;

    public function login()
    {
        $this->validate();
        $key = 'login:'.mb_strtolower($this->nip).'|'.request()->ip();
        $ipKey = 'login-ip:'.request()->ip();

        // 5 percobaan/menit per akun+IP, 20 percobaan/menit per IP (cegah brute force & password spraying).
        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            $wait = max(RateLimiter::availableIn($key), RateLimiter::availableIn($ipKey));
            throw ValidationException::withMessages(['nip' => 'Terlalu banyak percobaan. Coba lagi dalam '.$wait.' detik.']);
        }

        if (! Auth::attempt(['nip' => $this->nip, 'password' => $this->password, 'is_active' => true], $this->remember)) {
            RateLimiter::hit($key);
            RateLimiter::hit($ipKey);
            throw ValidationException::withMessages(['nip' => 'NIP atau password salah, atau akun tidak aktif.']);
        }

        RateLimiter::clear($key);
        session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->save();

        return $this->redirect($user->homeRoute());
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
