<?php

use App\Http\Controllers\FileController;
use App\Livewire\Admin;
use App\Livewire\Auth\Login;
use App\Livewire\Eselon;
use App\Livewire\Kadis;
use App\Livewire\Shared;
use App\Livewire\Staff;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect(Auth::user()->homeRoute()))->name('home');
    Route::view('/tanpa-jabatan', 'no-position')->name('no-position');

    Route::post('/logout', function () {
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    Route::get('/berkas/{type}/{id}', FileController::class)->name('file')->whereIn('type', ['arahan', 'tugas', 'laporan']);

    // Fitur bersama pejabat & pegawai
    Route::livewire('/instruksi/{recipient}', Shared\RecipientDetail::class)->middleware('level:kadis,eselon3,eselon4,staff')->name('recipient.show');
    Route::livewire('/notifikasi', Shared\Notifications::class)->middleware('level:kadis,eselon3,eselon4,staff')->name('notifications');
    Route::livewire('/anggota', Shared\Members::class)->middleware('level:eselon3,eselon4')->name('members');
    Route::livewire('/jadwal', Shared\Schedule::class)->middleware('level:kadis,eselon3,eselon4,staff')->name('schedule');

    Route::middleware('level:kadis')->prefix('kadis')->name('kadis.')->group(function () {
        Route::livewire('/', Kadis\Dashboard::class)->name('dashboard');
        Route::livewire('/arahan', Kadis\Directions::class)->name('directions');
        Route::livewire('/patuh', Kadis\Compliance::class)->name('compliance');
        Route::livewire('/pegawai', Kadis\Employees::class)->name('employees');
        Route::livewire('/peta', Kadis\Map::class)->name('map');
    });

    Route::middleware('level:eselon3')->prefix('eselon3')->name('eselon3.')->group(function () {
        Route::livewire('/', Eselon\Dashboard::class)->name('dashboard');
        Route::livewire('/arahan', Eselon\Directions::class)->name('directions');
    });

    Route::middleware('level:eselon4')->prefix('eselon4')->name('eselon4.')->group(function () {
        Route::livewire('/', Eselon\Dashboard::class)->name('dashboard');
        Route::livewire('/bagi-verifikasi', Eselon\Directions::class)->name('directions');
    });

    Route::middleware('level:staff')->prefix('staff')->name('staff.')->group(function () {
        Route::livewire('/', Staff\Dashboard::class)->name('dashboard');
        Route::livewire('/tugas', Staff\Tasks::class)->name('tasks');
        Route::livewire('/tupoksi', Staff\Duties::class)->name('duties');
    });

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::livewire('/', Admin\Dashboard::class)->name('dashboard');
        Route::livewire('/user', Admin\Users::class)->name('users');
        Route::livewire('/pegawai', Admin\Employees::class)->name('employees');
        Route::livewire('/struktur', Admin\Structure::class)->name('structure');
        Route::livewire('/tupoksi', Admin\Duties::class)->name('duties');
        Route::livewire('/pengaturan', Admin\Settings::class)->name('settings');
    });
});
