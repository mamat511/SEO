<?php

use App\Http\Controllers\GoogleController;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Projects;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');

    Route::get('/google/connect', [GoogleController::class, 'redirect'])->name('google.connect');
    Route::get('/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
    Route::post('/google/disconnect', [GoogleController::class, 'disconnect'])->name('google.disconnect');

    Route::prefix('projects/{project}')->name('projects.')->group(function () {
        Route::get('/', Projects\Overview::class)->name('overview');
        Route::get('/positions', Projects\Rankings::class)->name('rankings');
        Route::get('/backlinks', Projects\Backlinks::class)->name('backlinks');
        Route::get('/audit', Projects\Audit::class)->name('audit');
        Route::get('/reglages', Projects\Settings::class)->name('settings');
    });

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
