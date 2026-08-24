<?php

use Modules\Inventory\Actions\CancelOrderAction;
use Modules\Inventory\Actions\ConfirmOrderAction;
use Modules\Inventory\Actions\CreateShipmentAction;
use Modules\Inventory\Actions\DispatchShipmentAction;
use Modules\Inventory\Actions\TransitionShipmentAction;
use Modules\Inventory\Enums\ShipmentStatus;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Exceptions\AlreadyProcessedException;
use Modules\Inventory\Exceptions\InsufficientStockException;
use Modules\Inventory\Exceptions\InvalidStatusTransitionException;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Services\InventoryService;
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

    expect($order->refresh()->status->value)->toBe('confirmed')
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

it('deducts stock and releases the reservation when a shipment is dispatched', function () {
    [$product, $order] = orderWithStock(10, 4);
    app(ConfirmOrderAction::class)->handle($order);

    $shipment = app(CreateShipmentAction::class)->handle($order->refresh(), [
        'carrier' => 'UPS',
        'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 4]],
    ]);

    app(DispatchShipmentAction::class)->handle($shipment);

    $item = app(InventoryService::class)->itemFor(new StockableUnit($product->id));

    expect($item->quantity_on_hand)->toBe(6)
        ->and($item->quantity_reserved)->toBe(0)
        ->and($order->refresh()->status->value)->toBe('shipped')
        ->and($order->items->first()->refresh()->quantity_shipped)->toBe(4);

    $movement = StockMovement::query()->where('type', StockMovementType::ShipmentOut)->firstOrFail();

    expect($movement->type)->toBe(StockMovementType::ShipmentOut)
        ->and($movement->quantity)->toBe(-4);
});

it('does not deduct twice when a shipment is dispatched again', function () {
    [$product, $order] = orderWithStock(10, 3);
    app(ConfirmOrderAction::class)->handle($order);

    $shipment = app(CreateShipmentAction::class)->handle($order->refresh(), [
        'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 3]],
    ]);

    $dispatch = app(DispatchShipmentAction::class);
    $dispatch->handle($shipment);

    expect(fn () => $dispatch->handle($shipment->refresh()))
        ->toThrow(AlreadyProcessedException::class);

    expect(app(InventoryService::class)->onHandQuantity(new StockableUnit($product->id)))->toBe(7);
});

it('moves a partially shipped order to processing', function () {
    [, $order] = orderWithStock(10, 5);
    app(ConfirmOrderAction::class)->handle($order);

    $shipment = app(CreateShipmentAction::class)->handle($order->refresh(), [
        'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 2]],
    ]);

    app(DispatchShipmentAction::class)->handle($shipment);

    expect($order->refresh()->status->value)->toBe('processing');
});

it('releases the reservation when a confirmed order is cancelled', function () {
    [$product, $order] = orderWithStock(10, 4);
    app(ConfirmOrderAction::class)->handle($order);

    app(CancelOrderAction::class)->handle($order->refresh(), 'Customer changed their mind');

    $item = app(InventoryService::class)->itemFor(new StockableUnit($product->id));

    expect($order->refresh()->status->value)->toBe('cancelled')
        ->and($item->quantity_reserved)->toBe(0)
        ->and($item->quantity_on_hand)->toBe(10);
});

it('returns shipped units to stock when the order is cancelled', function () {
    [$product, $order] = orderWithStock(10, 4);
    app(ConfirmOrderAction::class)->handle($order);

    $shipment = app(CreateShipmentAction::class)->handle($order->refresh(), [
        'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 4]],
    ]);
    app(DispatchShipmentAction::class)->handle($shipment);

    app(CancelOrderAction::class)->handle($order->refresh(), 'Returned in full');

    $item = app(InventoryService::class)->itemFor(new StockableUnit($product->id));

    expect($item->quantity_on_hand)->toBe(10)
        ->and($item->quantity_reserved)->toBe(0)
        ->and($order->items->first()->refresh()->quantity_shipped)->toBe(0);
});

it('returns stock and re-reserves it when a dispatched shipment is cancelled', function () {
    [$product, $order] = orderWithStock(10, 4);
    app(ConfirmOrderAction::class)->handle($order);

    $shipment = app(CreateShipmentAction::class)->handle($order->refresh(), [
        'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 4]],
    ]);
    app(DispatchShipmentAction::class)->handle($shipment);

    // Dispatching a full shipment advances the order to "shipped", which no
    // longer holds a reservation, so only on-hand comes back.
    app(TransitionShipmentAction::class)->handle($shipment->refresh(), ShipmentStatus::Cancelled);

    $item = app(InventoryService::class)->itemFor(new StockableUnit($product->id));

    expect($item->quantity_on_hand)->toBe(10)
        ->and($shipment->refresh()->status->value)->toBe('cancelled')
        ->and($order->items->first()->refresh()->quantity_shipped)->toBe(0);
});

it('refuses to ship a shipment through the plain status endpoint', function () {
    [, $order] = orderWithStock(10, 1);
    app(ConfirmOrderAction::class)->handle($order);

    $shipment = app(CreateShipmentAction::class)->handle($order->refresh(), [
        'items' => [['order_item_id' => $order->items->first()->id, 'quantity' => 1]],
    ]);

    expect(fn () => app(TransitionShipmentAction::class)->handle($shipment, ShipmentStatus::Shipped))
        ->toThrow(InvalidStatusTransitionException::class);
});
