<?php

use App\Core\Support\Permissions;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Services\OrderService;
use Modules\Inventory\Support\OrderStatuses;

beforeEach(function () {
    $this->clerk = userWithPermissions([
        Permissions::ORDERS_VIEW,
        Permissions::ORDERS_CREATE,
        Permissions::ORDERS_UPDATE,
        Permissions::PRODUCTS_VIEW,
    ]);

    OrderStatuses::flush();
});

it('stores the configured id rather than the status name', function () {
    $order = Order::factory()->create();

    expect($order->getRawOriginal('status_id'))
        ->toBe(OrderStatuses::key('pending')->id)
        // The column holds a number; nothing anywhere stores "pending".
        ->and(is_int($order->status_id))->toBeTrue();
});

it('starts a new order in the configured default status', function () {
    $order = new Order(['order_number' => 'ORD-DEFAULT', 'customer_name' => 'Walk-in']);
    $order->save();

    expect($order->status->key)->toBe(config('orders.default'));
});

it('reads the lifecycle from config, so relabelling a status changes the UI only', function () {
    config()->set('orders.statuses.0.label', 'Estimate');
    OrderStatuses::flush();

    $order = Order::factory()->draft()->create();

    expect($order->status->label)->toBe('Estimate')
        // The stored id did not move, so no historical row was rewritten.
        ->and($order->status->id)->toBe(1);
});

it('drives the transition table from config', function () {
    $draft = OrderStatuses::key('draft');

    expect($draft->canTransitionTo('pending'))->toBeTrue()
        ->and($draft->canTransitionTo('completed'))->toBeFalse();

    config()->set('orders.statuses.0.transitions', ['completed']);
    OrderStatuses::flush();

    expect(OrderStatuses::key('draft')->canTransitionTo('completed'))->toBeTrue();
});

it('binds the inventory effects to the flags, not to the names', function () {
    expect(OrderStatuses::key('confirmed')->holdsReservation())->toBeTrue()
        ->and(OrderStatuses::key('confirmed')->isFulfillable())->toBeTrue()
        ->and(OrderStatuses::key('draft')->holdsReservation())->toBeFalse()
        ->and(OrderStatuses::key('draft')->isEditable())->toBeTrue()
        ->and(OrderStatuses::key('completed')->isEditable())->toBeFalse()
        ->and(OrderStatuses::key('cancelled')->isVoid())->toBeTrue()
        ->and(OrderStatuses::key('completed')->isVoid())->toBeFalse();
});

it('excludes void statuses from trade', function () {
    expect(OrderStatuses::billableIds())
        ->not->toContain(OrderStatuses::key('cancelled')->id)
        ->toContain(OrderStatuses::key('completed')->id);
});

it('resolves a status from an id, a key or the object', function () {
    $confirmed = OrderStatuses::key('confirmed');

    expect(OrderStatuses::resolve($confirmed->id)->key)->toBe('confirmed')
        ->and(OrderStatuses::resolve('confirmed')->id)->toBe($confirmed->id)
        ->and(OrderStatuses::resolve((string) $confirmed->id)->key)->toBe('confirmed')
        ->and(OrderStatuses::resolve($confirmed))->toBe($confirmed)
        ->and(OrderStatuses::resolve('nonsense'))->toBeNull();
});

it('serialises the status for the frontend', function () {
    $order = Order::factory()->create()->fresh();
    $payload = $order->toArray();

    expect($payload['status'])->toBe([
        'id' => OrderStatuses::key('pending')->id,
        'key' => 'pending',
        'label' => 'Pending',
        'variant' => 'warning',
    ])->and($payload)->toHaveKey('status_id');
});

it('only accepts a status a form is allowed to set', function () {
    $product = Product::factory()->create(['selling_price' => 10]);

    // "completed" is reached through fulfilment, never through the form.
    $this->actingAs($this->clerk)
        ->post('/inventory/orders', [
            'customer_name' => 'Walk-in',
            'status_id' => OrderStatuses::key('completed')->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertSessionHasErrors('status_id');
});

it('filters the order list by status id or key', function () {
    Order::factory()->draft()->create();
    Order::factory()->create();

    $service = app(OrderService::class);

    expect($service->paginate(['status' => OrderStatuses::key('draft')->id])->total())->toBe(1)
        ->and($service->paginate(['status' => 'draft'])->total())->toBe(1)
        ->and($service->paginate([])->total())->toBe(2);
});
