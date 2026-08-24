<?php

use App\Core\Support\Permissions;
use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\Api\DashboardController;
use Modules\Inventory\Http\Controllers\Api\ProductController;
use Modules\Inventory\Http\Controllers\Api\StockController;

/*
|--------------------------------------------------------------------------
| Inventory Module — JSON API
|--------------------------------------------------------------------------
|
| Registered under the "api/v1" prefix by App\Core\ModuleServiceProvider.
| Read-only for now: every write path (receiving, ordering, dispatching) is
| owned by the Inertia controllers so the inventory rules have exactly one
| implementation. Add write endpoints here by delegating to the same
| services/actions those controllers use — never by re-implementing the rules.
|
*/

Route::middleware(['auth'])->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:'.Permissions::DASHBOARD_VIEW)
        ->name('api.dashboard');

    Route::get('products', [ProductController::class, 'index'])
        ->middleware('permission:'.Permissions::PRODUCTS_VIEW)
        ->name('api.products.index');
    Route::get('products/{product}', [ProductController::class, 'show'])
        ->middleware('permission:'.Permissions::PRODUCTS_VIEW)
        ->name('api.products.show');

    Route::get('stock/items', [StockController::class, 'items'])
        ->middleware('permission:'.Permissions::INVENTORY_VIEW)
        ->name('api.stock.items');
    Route::get('stock/movements', [StockController::class, 'movements'])
        ->middleware('permission:'.Permissions::INVENTORY_VIEW)
        ->name('api.stock.movements');
});
