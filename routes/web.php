<?php

use App\Http\Controllers\PrototypeController;
use App\Support\PrototypeScreens;
use Illuminate\Support\Facades\Route;

Route::get('/', [PrototypeController::class, 'show'])
    ->defaults('screen', 'dashboard')
    ->name('home');

Route::view('/login', 'auth.login')->name('login');
Route::view('/recuperar-contrasena', 'auth.password')->name('password.request');
Route::view('/onboarding/empresa', 'onboarding.company')->name('onboarding.company');

foreach (PrototypeScreens::routes() as $screen) {
    Route::get($screen['uri'], [PrototypeController::class, 'show'])
        ->defaults('screen', $screen['key'])
        ->name($screen['route']);
}
