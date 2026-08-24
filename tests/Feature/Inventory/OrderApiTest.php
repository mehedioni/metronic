<?php

use App\Core\Support\Permissions;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Product;

it('creates an order through the endpoint and prices it from the product', function () {
    $user = userWithPermissions([Permissions::ORDERS_VIEW, Permissions::ORDERS_CREATE]);
    $product = Product::factory()->create(['selling_price' => 25]);

    $this->actingAs($user)->post('/inventory/orders', [
        'customer_name' => 'Cliff Booth',
        'items' => [['product_id' => $product->id, 'quantity' => 3]],
    ])->assertSessionHasNoErrors();

    $order = Order::query()->firstOrFail();

    expect($order->items)->toHaveCount(1)
        ->and((float) $order->items->first()->unit_price)->toBe(25.0)
        ->and((float) $order->subtotal)->toBe(75.0)
        ->and((float) $order->total)->toBe(75.0);
});

it('rejects an order for an archived product', function () {
    $user = userWithPermissions([Permissions::ORDERS_VIEW, Permissions::ORDERS_CREATE]);
    $product = Product::factory()->archived()->create();

    $this->actingAs($user)->post('/inventory/orders', [
        'customer_name' => 'Nobody',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('items.0.product_id');
});

it('requires at least one order line', function () {
    $user = userWithPermissions([Permissions::ORDERS_VIEW, Permissions::ORDERS_CREATE]);

    $this->actingAs($user)
        ->post('/inventory/orders', ['customer_name' => 'Nobody'])
        ->assertSessionHasErrors('items');
});

it('serves the read-only stock api to a permitted user', function () {
    $user = userWithPermissions([Permissions::INVENTORY_VIEW]);

    $this->actingAs($user)
        ->getJson('/api/v1/stock/items')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('denies the stock api to a user without the permission', function () {
    $user = userWithPermissions([Permissions::ORDERS_VIEW]);

    $this->actingAs($user)->getJson('/api/v1/stock/items')->assertForbidden();
});
