<?php

use App\Core\Support\Permissions;
use Modules\Inventory\Actions\AdjustStockAction;
use Modules\Inventory\Actions\CancelInboundReceiptAction;
use Modules\Inventory\Actions\ReceiveInboundReceiptAction;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Exceptions\AlreadyProcessedException;
use Modules\Inventory\Exceptions\InsufficientStockException;
use Modules\Inventory\Models\InboundReceipt;
use Modules\Inventory\Models\InboundReceiptItem;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Services\InventoryService;
use Modules\Inventory\Support\StockableUnit;

it('increases stock and records a movement', function () {
    $product = Product::factory()->create();
    $unit = new StockableUnit($product->id);

    app(InventoryService::class)->record($unit, StockMovementType::OpeningStock, 25);

    expect(app(InventoryService::class)->onHandQuantity($unit))->toBe(25);

    $movement = StockMovement::query()->firstOrFail();

    expect($movement->quantity)->toBe(25)
        ->and($movement->quantity_before)->toBe(0)
        ->and($movement->quantity_after)->toBe(25);
});

it('decreases stock and records the before and after quantities', function () {
    $product = Product::factory()->create();
    $unit = new StockableUnit($product->id);
    $inventory = app(InventoryService::class);

    $inventory->record($unit, StockMovementType::OpeningStock, 10);
    $inventory->record($unit, StockMovementType::Damage, 4);

    expect($inventory->onHandQuantity($unit))->toBe(6);

    $movement = StockMovement::query()->where('type', StockMovementType::Damage)->firstOrFail();

    expect($movement->quantity)->toBe(-4)
        ->and($movement->quantity_before)->toBe(10)
        ->and($movement->quantity_after)->toBe(6);
});

it('refuses to drive stock negative when negative stock is disabled', function () {
    config()->set('inventory.allow_negative_stock', false);

    $product = Product::factory()->create();
    $unit = new StockableUnit($product->id);

    app(InventoryService::class)->record($unit, StockMovementType::OpeningStock, 3);

    expect(fn () => app(InventoryService::class)->record($unit, StockMovementType::Damage, 5))
        ->toThrow(InsufficientStockException::class);

    expect(app(InventoryService::class)->onHandQuantity($unit))->toBe(3);
    expect(StockMovement::query()->count())->toBe(1);
});

it('keeps one inventory row per stockable unit', function () {
    $product = Product::factory()->create();
    $unit = new StockableUnit($product->id);
    $inventory = app(InventoryService::class);

    $inventory->record($unit, StockMovementType::OpeningStock, 2);
    $inventory->record($unit, StockMovementType::AdjustmentIncrease, 3);

    expect(InventoryItem::query()->count())->toBe(1)
        ->and($inventory->onHandQuantity($unit))->toBe(5);
});

it('tracks variants independently of their product', function () {
    $product = Product::factory()->variable()->create();
    $variant = $product->variants()->create([
        'sku' => 'VAR-1',
        'name' => 'Small',
    ]);

    $inventory = app(InventoryService::class);
    $inventory->record(new StockableUnit($product->id, $variant->id), StockMovementType::OpeningStock, 7);

    expect($inventory->onHandQuantity(new StockableUnit($product->id, $variant->id)))->toBe(7)
        ->and($inventory->onHandQuantity(new StockableUnit($product->id)))->toBe(0)
        ->and(InventoryItem::query()->count())->toBe(2);
});

it('adds stock when a supplier receipt is received', function () {
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();

    $receipt = InboundReceipt::factory()->create(['supplier_id' => $supplier->id]);
    InboundReceiptItem::factory()->create([
        'inbound_receipt_id' => $receipt->id,
        'product_id' => $product->id,
        'quantity' => 40,
        'unit_cost' => 3.5,
    ]);

    app(ReceiveInboundReceiptAction::class)->handle($receipt);

    expect(app(InventoryService::class)->onHandQuantity(new StockableUnit($product->id)))->toBe(40);

    $movement = StockMovement::query()->firstOrFail();

    expect($movement->type)->toBe(StockMovementType::StockReceived)
        ->and($movement->supplier_id)->toBe($supplier->id)
        ->and($movement->reference_type)->toBe(InboundReceipt::class)
        ->and($movement->reference_id)->toBe($receipt->id);
});

it('does not double count stock when a receipt is processed twice', function () {
    $product = Product::factory()->create();
    $receipt = InboundReceipt::factory()->create();
    InboundReceiptItem::factory()->create([
        'inbound_receipt_id' => $receipt->id,
        'product_id' => $product->id,
        'quantity' => 12,
    ]);

    $action = app(ReceiveInboundReceiptAction::class);
    $action->handle($receipt);

    expect(fn () => $action->handle($receipt->refresh()))
        ->toThrow(AlreadyProcessedException::class);

    expect(app(InventoryService::class)->onHandQuantity(new StockableUnit($product->id)))->toBe(12)
        ->and(StockMovement::query()->count())->toBe(1);
});

it('reverses stock when a processed receipt is cancelled', function () {
    $product = Product::factory()->create();
    $receipt = InboundReceipt::factory()->create();
    InboundReceiptItem::factory()->create([
        'inbound_receipt_id' => $receipt->id,
        'product_id' => $product->id,
        'quantity' => 9,
    ]);

    app(ReceiveInboundReceiptAction::class)->handle($receipt);
    app(CancelInboundReceiptAction::class)->handle($receipt->refresh(), 'Wrong delivery');

    expect(app(InventoryService::class)->onHandQuantity(new StockableUnit($product->id)))->toBe(0)
        ->and(StockMovement::query()->count())->toBe(2)
        ->and($receipt->refresh()->status->value)->toBe('cancelled');
});

it('records the acting user on a manual adjustment', function () {
    $user = userWithPermissions([Permissions::INVENTORY_ADJUST]);
    $product = Product::factory()->create();

    app(AdjustStockAction::class)->handle(
        new StockableUnit($product->id),
        StockMovementType::OpeningStock,
        5,
        'Initial count',
        $user->id,
    );

    $movement = StockMovement::query()->firstOrFail();

    expect($movement->user_id)->toBe($user->id)
        ->and($movement->reason)->toBe('Initial count');
});

it('requires a reason when stock is removed through the adjust endpoint', function () {
    $user = userWithPermissions([Permissions::INVENTORY_VIEW, Permissions::INVENTORY_ADJUST]);
    $product = Product::factory()->create();

    $this->actingAs($user)->post('/inventory/stock/adjust', [
        'product_id' => $product->id,
        'type' => StockMovementType::Damage->value,
        'quantity' => 1,
    ])->assertSessionHasErrors('reason');
});

it('rejects an adjustment from a user without the adjust permission', function () {
    $user = userWithPermissions([Permissions::INVENTORY_VIEW]);
    $product = Product::factory()->create();

    $this->actingAs($user)->post('/inventory/stock/adjust', [
        'product_id' => $product->id,
        'type' => StockMovementType::OpeningStock->value,
        'quantity' => 5,
    ])->assertForbidden();

    expect(StockMovement::query()->count())->toBe(0);
});
