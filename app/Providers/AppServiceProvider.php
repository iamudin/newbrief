<?php

namespace App\Providers;

use App\Http\Middleware\EnsureAccountIsUsable;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Middleware akun juga dijalankan ulang pada setiap request update Livewire.
        Livewire::addPersistentMiddleware([EnsureAccountIsUsable::class]);

        // Aturan password bawaan untuk seluruh aplikasi.
        Password::defaults(fn () => Password::min(8)->letters()->numbers()->max(72));
        //
    }
}
