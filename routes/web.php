<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\PrototypeController;
use App\Support\PrototypeScreens;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/recuperar-contrasena', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/recuperar-contrasena', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/restablecer-contrasena/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/restablecer-contrasena', [NewPasswordController::class, 'store'])->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::view('/onboarding/empresa', 'onboarding.company')->name('onboarding.company');

Route::middleware('auth')->group(function (): void {
    Route::get('/', [PrototypeController::class, 'show'])
        ->defaults('screen', 'dashboard')
        ->name('home');

    foreach (PrototypeScreens::routes() as $screen) {
        $route = Route::get($screen['uri'], [PrototypeController::class, 'show'])
            ->defaults('screen', $screen['key'])
            ->name($screen['route']);

        if (isset($screen['permission'])) {
            $route->middleware('can:'.$screen['permission']);
        }
    }
});
