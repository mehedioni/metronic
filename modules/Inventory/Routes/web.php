<?php

use App\Core\Support\Permissions;
use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\CategoryController;
use Modules\Inventory\Http\Controllers\CustomerController;
use Modules\Inventory\Http\Controllers\DashboardController;
use Modules\Inventory\Http\Controllers\ExpenseController;
use Modules\Inventory\Http\Controllers\InboundReceiptController;
use Modules\Inventory\Http\Controllers\InventoryController;
use Modules\Inventory\Http\Controllers\OrderController;
use Modules\Inventory\Http\Controllers\ProductController;
use Modules\Inventory\Http\Controllers\QuoteController;
use Modules\Inventory\Http\Controllers\ReportController;
use Modules\Inventory\Http\Controllers\StockMovementController;
use Modules\Inventory\Http\Controllers\SupplierController;

/*
|--------------------------------------------------------------------------
| Inventory Module — Inertia Routes
|--------------------------------------------------------------------------
|
| Every route is protected twice: the "permission" middleware rejects the
| request before the controller runs, and the controller's resource policy
| re-checks the ability. Middleware alone is not enough — a policy is what
| protects a record reached through a nested or custom route.
|
*/

Route::middleware(['auth'])->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:'.Permissions::DASHBOARD_VIEW)
        ->name('dashboard');
});

Route::middleware(['auth'])->prefix('inventory')->name('inventory.')->group(function (): void {
    Route::resource('categories', CategoryController::class)
        ->middleware('permission:'.Permissions::CATEGORIES_VIEW);

    Route::resource('suppliers', SupplierController::class)
        ->except(['create', 'edit'])
        ->middleware('permission:'.Permissions::SUPPLIERS_VIEW);
    Route::patch('suppliers/{supplier}/status', [SupplierController::class, 'toggleStatus'])
        ->middleware('permission:'.Permissions::SUPPLIERS_UPDATE)
        ->name('suppliers.status');

    Route::resource('customers', CustomerController::class)
        ->except(['create', 'edit'])
        ->middleware('permission:'.Permissions::CUSTOMERS_VIEW);
    Route::patch('customers/{customer}/status', [CustomerController::class, 'toggleStatus'])
        ->middleware('permission:'.Permissions::CUSTOMERS_UPDATE)
        ->name('customers.status');

    Route::resource('products', ProductController::class)
        ->middleware('permission:'.Permissions::PRODUCTS_VIEW);

    Route::get('stock', [InventoryController::class, 'index'])
        ->middleware('permission:'.Permissions::INVENTORY_VIEW)
        ->name('stock.index');
    Route::get('stock/planner', [InventoryController::class, 'planner'])
        ->middleware('permission:'.Permissions::INVENTORY_VIEW)
        ->name('stock.planner');
    Route::post('stock/adjust', [InventoryController::class, 'adjust'])
        ->middleware('permission:'.Permissions::INVENTORY_ADJUST)
        ->name('stock.adjust');

    Route::get('movements', [StockMovementController::class, 'index'])
        ->middleware('permission:'.Permissions::INVENTORY_VIEW)
        ->name('movements.index');

    Route::resource('inbound', InboundReceiptController::class)
        ->parameters(['inbound' => 'receipt'])
        ->except(['create', 'edit'])
        ->middleware('permission:'.Permissions::INVENTORY_VIEW);
    Route::post('inbound/{receipt}/receive', [InboundReceiptController::class, 'receive'])
        ->middleware('permission:'.Permissions::INVENTORY_CREATE)
        ->name('inbound.receive');
    Route::post('inbound/{receipt}/cancel', [InboundReceiptController::class, 'cancel'])
        ->middleware('permission:'.Permissions::INVENTORY_ADJUST)
        ->name('inbound.cancel');

    Route::resource('expenses', ExpenseController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middleware('permission:'.Permissions::EXPENSES_VIEW);

    Route::get('reports/daily', [ReportController::class, 'daily'])
        ->middleware('permission:'.Permissions::REPORTS_VIEW)
        ->name('reports.daily');

    // Quotes are orders in the configured quote status; the routes sit
    // beside orders rather than nesting, because a quote becomes an order in
    // place and keeps its own detail screen.
    Route::get('quotes', [QuoteController::class, 'index'])
        ->middleware('permission:'.Permissions::ORDERS_VIEW)
        ->name('quotes.index');
    Route::get('quotes/create', [QuoteController::class, 'create'])
        ->middleware('permission:'.Permissions::ORDERS_CREATE)
        ->name('quotes.create');
    Route::post('quotes', [QuoteController::class, 'store'])
        ->middleware('permission:'.Permissions::ORDERS_CREATE)
        ->name('quotes.store');

    Route::resource('orders', OrderController::class)
        ->middleware('permission:'.Permissions::ORDERS_VIEW);
    Route::post('orders/{order}/confirm', [OrderController::class, 'confirm'])
        ->middleware('permission:'.Permissions::ORDERS_UPDATE)
        ->name('orders.confirm');
    Route::post('orders/{order}/fulfill', [OrderController::class, 'fulfill'])
        ->middleware('permission:'.Permissions::ORDERS_FULFILL)
        ->name('orders.fulfill');
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])
        ->middleware('permission:'.Permissions::ORDERS_CANCEL)
        ->name('orders.cancel');
});
