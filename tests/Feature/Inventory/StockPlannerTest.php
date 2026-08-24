<?php

use App\Core\Support\Permissions;
use Illuminate\Support\Str;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Services\InventoryService;
use Modules\Inventory\Services\StockPlannerService;
use Modules\Inventory\Support\StockableUnit;

beforeEach(function () {
    $this->planner = userWithPermissions([Permissions::INVENTORY_VIEW]);
});

/**
 * A product holding $onHand units that has sold $sold of them inside the
 * 30-day velocity window.
 */
function plannedProduct(int $onHand, int $sold, int $threshold = 10): Product
{
    $product = Product::factory()->create(['low_stock_threshold' => $threshold]);
    $unit = new StockableUnit($product->id);
    $inventory = app(InventoryService::class);

    $inventory->record($unit, StockMovementType::OpeningStock, $onHand + $sold);

    if ($sold > 0) {
        $inventory->record($unit, StockMovementType::ManualRemoval, $sold);
    }

    return $product;
}

it('derives velocity, cover and reorder quantity from the ledger', function () {
    // 30 units sold across the 30-day window is one unit a day.
    $product = plannedProduct(onHand: 20, sold: 30, threshold: 10);

    $rows = app(StockPlannerService::class)->paginate([]);
    $plan = $rows->getCollection()->firstWhere('product_id', $product->id)->plan;

    expect($plan['daily_velocity'])->toBe(1.0)
        ->and($plan['available'])->toBe(20)
        ->and($plan['target_level'])->toBe(10)
        ->and($plan['delta'])->toBe(10)
        ->and($plan['days_of_cover'])->toBe(20)
        // No supplier link states a lead time, so the 7-day default applies:
        // 10 target + 7 days of cover = 17 required, 20 available.
        ->and($plan['lead_time_days'])->toBe(7)
        ->and($plan['reorder_quantity'])->toBe(0)
        ->and($plan['needs_reorder'])->toBeFalse();
});

it('asks for a reorder once cover no longer spans the lead time', function () {
    $product = plannedProduct(onHand: 5, sold: 30, threshold: 10);

    $rows = app(StockPlannerService::class)->paginate([]);
    $plan = $rows->getCollection()->firstWhere('product_id', $product->id)->plan;

    // 10 target + 7 days at 1/day = 17 required, 5 available.
    expect($plan['reorder_quantity'])->toBe(12)
        ->and($plan['needs_reorder'])->toBeTrue()
        ->and($plan['days_of_cover'])->toBe(5);
});

it('takes the lead time from the supplier link', function () {
    $product = plannedProduct(onHand: 5, sold: 30, threshold: 10);
    $supplier = Supplier::factory()->create();

    $product->suppliers()->attach($supplier->id, [
        'id' => Str::uuid()->toString(),
        'variant_key' => '',
        'lead_time_days' => 21,
        'is_preferred' => true,
    ]);

    $rows = app(StockPlannerService::class)->paginate([]);
    $plan = $rows->getCollection()->firstWhere('product_id', $product->id)->plan;

    // 10 target + 21 days at 1/day = 31 required, 5 available.
    expect($plan['lead_time_days'])->toBe(21)
        ->and($plan['reorder_quantity'])->toBe(26);
});

it('reports no cover for a unit that never moves', function () {
    $product = plannedProduct(onHand: 8, sold: 0, threshold: 5);

    $rows = app(StockPlannerService::class)->paginate([]);
    $plan = $rows->getCollection()->firstWhere('product_id', $product->id)->plan;

    expect($plan['daily_velocity'])->toBe(0.0)
        ->and($plan['days_of_cover'])->toBeNull()
        ->and($plan['reorder_quantity'])->toBe(0);
});

it('keeps only rows needing a reorder inside the requested horizon', function () {
    plannedProduct(onHand: 2, sold: 30, threshold: 10);   // 2 days of cover
    plannedProduct(onHand: 60, sold: 30, threshold: 10);  // 60 days of cover

    $rows = app(StockPlannerService::class)->paginate(['reorder_within' => 7]);

    expect($rows->getCollection())->toHaveCount(1);
});

it('serves the planner screen to a user who may view inventory', function () {
    plannedProduct(onHand: 2, sold: 30);

    $this->actingAs($this->planner)
        ->get('/inventory/stock/planner')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Inventory::Stock/Planner'));
});

it('denies the planner screen without the inventory permission', function () {
    $this->actingAs(userWithPermissions([]))
        ->get('/inventory/stock/planner')
        ->assertForbidden();
});
