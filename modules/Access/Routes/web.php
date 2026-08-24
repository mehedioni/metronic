<?php

use App\Core\Support\Permissions;
use Illuminate\Support\Facades\Route;
use Modules\Access\Http\Controllers\PermissionController;
use Modules\Access\Http\Controllers\RoleController;
use Modules\Access\Http\Controllers\UserController;

/*
|--------------------------------------------------------------------------
| Access Module — Inertia Routes
|--------------------------------------------------------------------------
|
| User, role and permission administration. Guarded by permission middleware
| and again by the module's policies, so a request that reaches a controller
| still cannot act on a record it is not allowed to touch.
|
*/

Route::middleware(['auth'])->prefix('access')->name('access.')->group(function (): void {
    Route::resource('users', UserController::class)
        ->except(['create', 'edit'])
        ->middleware('permission:'.Permissions::USERS_VIEW);
    Route::patch('users/{user}/status', [UserController::class, 'toggleActive'])
        ->middleware('permission:'.Permissions::USERS_UPDATE)
        ->name('users.status');

    Route::resource('roles', RoleController::class)
        ->except(['create', 'edit'])
        ->middleware('permission:'.Permissions::ROLES_VIEW);

    Route::get('permissions', [PermissionController::class, 'index'])
        ->middleware('permission:'.Permissions::PERMISSIONS_VIEW)
        ->name('permissions.index');
});
