<?php

use App\Core\Support\Permissions;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Authentication and account routes. Feature routes are owned by their
| module (modules/<Module>/Routes/web.php) and registered automatically by
| App\Core\ModuleServiceProvider.
|
| There is deliberately no registration route: accounts are created by an
| administrator through the Access module.
|
*/

Route::redirect('/', '/dashboard')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    /*
     * Settings live in a drawer over whichever page the user is on, so these
     * are writes only — there is no settings page to visit. The store's
     * settings are a permission; a user's own profile never is.
     */
    Route::put('settings/general', [SettingsController::class, 'updateGeneral'])
        ->middleware('permission:'.Permissions::SETTINGS_MANAGE)
        ->name('settings.general.update');

    Route::put('settings/profile', [SettingsController::class, 'updateProfile'])
        ->name('settings.profile.update');

    Route::get('verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});
