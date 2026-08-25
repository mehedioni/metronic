<?php

use Modules\Inventory\Actions\CancelOrderAction;
use Modules\Inventory\Actions\ConfirmOrderAction;
use Modules\Inventory\Actions\FulfillOrderAction;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Exceptions\AlreadyProcessedException;
use Modules\Inventory\Exceptions\InsufficientStockException;
use Modules\Inventory\Exceptions\InvalidStatusTransitionException;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Services\InventoryService;
use Modules\Inventory\Support\OrderStatuses;
use Modules\Inventory\Support\StockableUnit;

/**
 * Puts a known quantity on the shelf and returns the product plus an order
 * for part of it.
 *
 * @return array{0: Product, 1: Order}
 */
function orderWithStock(int $onHand, int $ordered): array
{
    $product = Product::factory()->create();

    app(InventoryService::class)->record(
        new StockableUnit($product->id),
        StockMovementType::OpeningStock,
        $onHand,
    );

    $order = Order::factory()->create();
    $order->items()->create([
        'product_id' => $product->id,
        'quantity' => $ordered,
        'unit_price' => 10,
        'line_total' => 10 * $ordered,
    ]);

    return [$product, $order->refresh()];
}

it('reserves stock when an order is confirmed', function () {
    [$product, $order] = orderWithStock(10, 4);

    app(ConfirmOrderAction::class)->handle($order);

    $item = app(InventoryService::class)->itemFor(new StockableUnit($product->id));

    expect($order->refresh()->status->key)->toBe('confirmed')
        ->and($item->quantity_on_hand)->toBe(10)
        ->and($item->quantity_reserved)->toBe(4)
        ->and($item->availableQuantity())->toBe(6)
        ->and(StockMovement::query()->count())->toBe(1);
});

it('prevents overselling on confirmation', function () {
    [$product, $order] = orderWithStock(2, 5);

    expect(fn () => app(ConfirmOrderAction::class)->handle($order))
        ->toThrow(InsufficientStockException::class);

    $item = app(InventoryService::class)->itemFor(new StockableUnit($product->id));

    expect($item->quantity_reserved)->toBe(0);
});

it('refuses to confirm the same order twice', function () {
    [, $order] = orderWithStock(10, 2);

    $action = app(ConfirmOrderAction::class);
    $action->handle($order);

    expect(fn () => $action->handle($order->refresh()))
        ->toThrow(InvalidStatusTransitionException::class);
});

it('deducts stock and releases the reservation when an order is fulfilled', function () {
    [$product, $order] = orderWithStock(10, 4);
    app(ConfirmOrderAction::class)->handle($order);

    app(FulfillOrderAction::class)->handle($order->refresh());

    $item = app(InventoryService::class)->itemFor(new StockableUnit($product->id));

    expect($item->quantity_on_hand)->toBe(6)
        ->and($item->quantity_reserved)->toBe(0)
        ->and($order->refresh()->status->key)->toBe('completed')
        ->and($order->refresh()->completed_at)->not->toBeNull()
        ->and($order->items->first()->refresh()->quantity_fulfilled)->toBe(4);

    $movement = StockMovement::query()->where('type', StockMovementType::OrderOut)->firstOrFail();

    expect($movement->type)->toBe(StockMovementType::OrderOut)
        ->and($movement->quantity)->toBe(-4)
        ->and($movement->reference_id)->toBe($order->id);
});

it('does not deduct twice when an order is fulfilled again', function () {
    [$product, $order] = orderWithStock(10, 3);
    app(ConfirmOrderAction::class)->handle($order);

    $fulfill = app(FulfillOrderAction::class);
    $fulfill->handle($order->refresh());

    expect(fn () => $fulfill->handle($order->refresh()))
        ->toThrow(AlreadyProcessedException::class);

    expect(app(InventoryService::class)->onHandQuantity(new StockableUnit($product->id)))->toBe(7);
});

it('refuses to fulfil an order that was never confirmed', function () {
    [$product, $order] = orderWithStock(10, 3);

    expect(fn () => app(FulfillOrderAction::class)->handle($order))
        ->toThrow(InvalidStatusTransitionException::class);

    expect(app(InventoryService::class)->onHandQuantity(new StockableUnit($product->id)))->toBe(10);
});

it('moves a partially fulfilled order to processing and keeps the rest reserved', function () {
    [$product, $order] = orderWithStock(10, 5);
    app(ConfirmOrderAction::class)->handle($order);

    $line = $order->items->first();

    app(FulfillOrderAction::class)->handle($order->refresh(), [$line->id => 2]);

    $item = app(InventoryService::class)->itemFor(new StockableUnit($product->id));

    expect($order->refresh()->status->key)->toBe('processing')
        ->and($item->quantity_on_hand)->toBe(8)
        ->and($item->quantity_reserved)->toBe(3)
        ->and($line->refresh()->quantity_fulfilled)->toBe(2)
        ->and($order->refresh()->completed_at)->toBeNull();
});

it('completes the order once the remaining lines are fulfilled', function () {
    [$product, $order] = orderWithStock(10, 5);
    app(ConfirmOrderAction::class)->handle($order);

    $line = $order->items->first();
    $fulfill = app(FulfillOrderAction::class);

    $fulfill->handle($order->refresh(), [$line->id => 2]);
    $fulfill->handle($order->refresh());

    $item = app(InventoryService::class)->itemFor(new StockableUnit($product->id));

    expect($order->refresh()->status->key)->toBe('completed')
        ->and($item->quantity_on_hand)->toBe(5)
        ->and($item->quantity_reserved)->toBe(0)
        ->and($line->refresh()->quantity_fulfilled)->toBe(5);
});

it('never fulfils more than a line still has outstanding', function () {
    [$product, $order] = orderWithStock(10, 3);
    app(ConfirmOrderAction::class)->handle($order);

    $line = $order->items->first();

    app(FulfillOrderAction::class)->handle($order->refresh(), [$line->id => 99]);

    expect($line->refresh()->quantity_fulfilled)->toBe(3)
        ->and(app(InventoryService::class)->onHandQuantity(new StockableUnit($product->id)))->toBe(7);
});

it('returns fulfilled units to stock when a partly fulfilled order is cancelled', function () {
    [$product, $order] = orderWithStock(10, 5);
    app(ConfirmOrderAction::class)->handle($order);

    $line = $order->items->first();
    app(FulfillOrderAction::class)->handle($order->refresh(), [$line->id => 2]);

    app(CancelOrderAction::class)->handle($order->refresh(), 'Returned in full');

    $item = app(InventoryService::class)->itemFor(new StockableUnit($product->id));

    expect($order->refresh()->status->key)->toBe('cancelled')
        ->and($item->quantity_on_hand)->toBe(10)
        ->and($item->quantity_reserved)->toBe(0)
        ->and($line->refresh()->quantity_fulfilled)->toBe(0);
});

it('fulfils an order that is already processing', function () {
    [$product, $order] = orderWithStock(10, 4);
    app(ConfirmOrderAction::class)->handle($order);
    $order->refresh()->forceFill(['status_id' => OrderStatuses::key('processing')->id])->save();

    app(FulfillOrderAction::class)->handle($order->refresh());

    expect($order->refresh()->status->key)->toBe('completed')
        ->and(app(InventoryService::class)->onHandQuantity(new StockableUnit($product->id)))->toBe(6);
});

it('releases the reservation when a confirmed order is cancelled', function () {
    [$product, $order] = orderWithStock(10, 4);
    app(ConfirmOrderAction::class)->handle($order);

    app(CancelOrderAction::class)->handle($order->refresh(), 'Customer changed their mind');

    $item = app(InventoryService::class)->itemFor(new StockableUnit($product->id));

    expect($order->refresh()->status->key)->toBe('cancelled')
        ->and($item->quantity_reserved)->toBe(0)
        ->and($item->quantity_on_hand)->toBe(10);
});

it('refuses to cancel a completed order', function () {
    [$product, $order] = orderWithStock(10, 4);
    app(ConfirmOrderAction::class)->handle($order);
    app(FulfillOrderAction::class)->handle($order->refresh());

    expect(fn () => app(CancelOrderAction::class)->handle($order->refresh(), 'Too late'))
        ->toThrow(InvalidStatusTransitionException::class);

    expect(app(InventoryService::class)->onHandQuantity(new StockableUnit($product->id)))->toBe(6);
});
