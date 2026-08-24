<?php

use App\Core\Support\Permissions;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Services\InventoryService;
use Modules\Inventory\Support\StockableUnit;

beforeEach(function () {
    $this->adjuster = userWithPermissions([
        Permissions::INVENTORY_VIEW,
        Permissions::INVENTORY_ADJUST,
    ]);
});

it('adjusts stock for a unit that has never held any', function () {
    // Regression: the adjust ability is authorized against the class, because
    // the inventory_items row may not exist yet. A policy method that required
    // the model died with an ArgumentCountError — a 500 — before the ability
    // was ever evaluated, so adjustment was unreachable.
    $product = Product::factory()->create();

    $this->actingAs($this->adjuster)
        ->post('/inventory/stock/adjust', [
            'product_id' => $product->id,
            'type' => StockMovementType::OpeningStock->value,
            'quantity' => 12,
            'reason' => 'Counted on the shelf',
        ])
        ->assertSessionHasNoErrors();

    $unit = new StockableUnit($product->id);

    expect(app(InventoryService::class)->onHandQuantity($unit))->toBe(12)
        ->and(StockMovement::query()->where('product_id', $product->id)->count())->toBe(1);
});

it('records an outbound adjustment against existing stock', function () {
    $product = Product::factory()->create();
    $unit = new StockableUnit($product->id);

    app(InventoryService::class)->record($unit, StockMovementType::OpeningStock, 20);

    $this->actingAs($this->adjuster)
        ->post('/inventory/stock/adjust', [
            'product_id' => $product->id,
            'type' => StockMovementType::Damage->value,
            'quantity' => 5,
            'reason' => 'Water damage',
        ])
        ->assertSessionHasNoErrors();

    expect(app(InventoryService::class)->onHandQuantity($unit))->toBe(15);
});

it('refuses an adjustment that would drive stock negative', function () {
    $product = Product::factory()->create();
    $unit = new StockableUnit($product->id);

    app(InventoryService::class)->record($unit, StockMovementType::OpeningStock, 3);

    $this->actingAs($this->adjuster)
        ->post('/inventory/stock/adjust', [
            'product_id' => $product->id,
            'type' => StockMovementType::Damage->value,
            'quantity' => 10,
            'reason' => 'Too many',
        ])
        ->assertSessionHasErrors('quantity');

    expect(app(InventoryService::class)->onHandQuantity($unit))->toBe(3);
});

it('denies an adjustment without the inventory.adjust permission', function () {
    $product = Product::factory()->create();

    $this->actingAs(userWithPermissions([Permissions::INVENTORY_VIEW]))
        ->post('/inventory/stock/adjust', [
            'product_id' => $product->id,
            'type' => StockMovementType::OpeningStock->value,
            'quantity' => 5,
        ])
        ->assertForbidden();
});

it('rejects a system-driven movement type from the adjustment endpoint', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->adjuster)
        ->post('/inventory/stock/adjust', [
            'product_id' => $product->id,
            'type' => StockMovementType::OrderOut->value,
            'quantity' => 1,
        ])
        ->assertSessionHasErrors('type');
});
