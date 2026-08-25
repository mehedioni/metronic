<?php

use App\Core\Support\Permissions;
use Modules\Inventory\Actions\ConfirmOrderAction;
use Modules\Inventory\Actions\FulfillOrderAction;
use Modules\Inventory\Enums\ProductStatus;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Models\Customer;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductVariant;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Services\InventoryService;
use Modules\Inventory\Support\OrderStatuses;
use Modules\Inventory\Support\StockableUnit;

beforeEach(function () {
    $this->clerk = userWithPermissions([
        Permissions::ORDERS_VIEW,
        Permissions::ORDERS_CREATE,
        Permissions::ORDERS_UPDATE,
        Permissions::PRODUCTS_VIEW,
        Permissions::CUSTOMERS_VIEW,
    ]);
});

it('serves the take-order screen with the catalogue and stock levels', function () {
    $product = Product::factory()->create();

    app(InventoryService::class)->record(
        new StockableUnit($product->id),
        StockMovementType::OpeningStock,
        7,
    );

    $this->actingAs($this->clerk)
        ->get('/inventory/orders/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Inventory::Orders/Create')
            // The form shows what is available to promise, so the stock rows
            // have to travel with the product options.
            ->has('options.products.0.inventory_items')
            ->has('options.customers')
            ->has('options.statuses'));
});

it('takes an order for a customer and prices it from the catalogue', function () {
    $customer = Customer::factory()->create([
        'name' => 'Emma Chen',
        'email' => 'emma@example.test',
        'phone' => '+15550001111',
    ]);
    $product = Product::factory()->create(['selling_price' => 25]);

    $this->actingAs($this->clerk)
        ->post('/inventory/orders', [
            'customer_id' => $customer->id,
            'status_id' => OrderStatuses::key('draft')->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])
        ->assertSessionHasNoErrors();

    $order = Order::query()->with('items')->firstOrFail();
    $line = $order->items->first();

    expect($order->status->key)->toBe('draft')
        ->and($order->customer_id)->toBe($customer->id)
        // Contact details are snapshotted, so the order still reads correctly
        // after the customer record changes.
        ->and($order->customer_name)->toBe('Emma Chen')
        ->and($order->customer_email)->toBe('emma@example.test')
        ->and($line->unit_price)->toBe('25.00')
        ->and($line->line_total)->toBe('75.00')
        ->and($order->subtotal)->toBe('75.00')
        ->and($order->total)->toBe('75.00')
        ->and($order->order_number)->toStartWith('ORD-');
});

it('snapshots the cost price onto the line, so a later cost change cannot restate the margin', function () {
    $product = Product::factory()->create([
        'selling_price' => 50,
        'cost_price' => 20,
    ]);

    $this->actingAs($this->clerk)->post('/inventory/orders', [
        'customer_name' => 'Walk-in',
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ])->assertSessionHasNoErrors();

    $line = Order::query()->firstOrFail()->items()->firstOrFail();

    expect($line->unit_cost)->toBe('20.00')
        ->and($line->lineCost())->toBe(40.0);

    // The supplier puts their prices up; the order already placed must not move.
    $product->update(['cost_price' => 45]);

    expect($line->refresh()->unit_cost)->toBe('20.00');
});

it('prefers the variant cost over the product cost', function () {
    $product = Product::factory()->variable()->create(['cost_price' => 10]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'cost_price' => 33,
        'selling_price' => 60,
    ]);

    $this->actingAs($this->clerk)->post('/inventory/orders', [
        'customer_name' => 'Walk-in',
        'items' => [
            [
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ],
        ],
    ])->assertSessionHasNoErrors();

    expect(Order::query()->firstOrFail()->items()->firstOrFail()->unit_cost)
        ->toBe('33.00');
});

it('leaves the cost null when nothing states one, rather than recording it as free', function () {
    $product = Product::factory()->create([
        'selling_price' => 12,
        'cost_price' => null,
    ]);

    $this->actingAs($this->clerk)->post('/inventory/orders', [
        'customer_name' => 'Walk-in',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertSessionHasNoErrors();

    $line = Order::query()->firstOrFail()->items()->firstOrFail();

    expect($line->unit_cost)->toBeNull()
        ->and($line->lineCost())->toBeNull();
});

it('carries the line cost onto the fulfilment ledger row', function () {
    $product = Product::factory()->create(['selling_price' => 50, 'cost_price' => 18]);

    app(InventoryService::class)->record(
        new StockableUnit($product->id),
        StockMovementType::OpeningStock,
        5,
    );

    // Confirmation is reached from pending; a draft has to be moved on first.
    $this->actingAs($this->clerk)->post('/inventory/orders', [
        'customer_name' => 'Walk-in',
        'status_id' => OrderStatuses::key('pending')->id,
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ]);

    $order = Order::query()->firstOrFail();

    app(ConfirmOrderAction::class)->handle($order);
    app(FulfillOrderAction::class)->handle($order->refresh());

    $movement = StockMovement::query()
        ->where('type', StockMovementType::OrderOut)
        ->firstOrFail();

    expect($movement->unit_cost)->toBe('18.00');
});

it('takes a walk-in order with only a name', function () {
    $product = Product::factory()->create(['selling_price' => 10]);

    $this->actingAs($this->clerk)
        ->post('/inventory/orders', [
            'customer_name' => 'Counter sale',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertSessionHasNoErrors();

    $order = Order::query()->firstOrFail();

    expect($order->customer_id)->toBeNull()
        ->and($order->customer_name)->toBe('Counter sale');
});

it('requires a customer or a name', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->clerk)
        ->post('/inventory/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertSessionHasErrors('customer_name');
});

it('requires at least one line', function () {
    $this->actingAs($this->clerk)
        ->post('/inventory/orders', ['customer_name' => 'Walk-in', 'items' => []])
        ->assertSessionHasErrors('items');
});

it('applies discount and tax to the order total', function () {
    $product = Product::factory()->create(['selling_price' => 100]);

    $this->actingAs($this->clerk)
        ->post('/inventory/orders', [
            'customer_name' => 'Walk-in',
            'discount_total' => 15,
            'tax_total' => 5,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])
        ->assertSessionHasNoErrors();

    $order = Order::query()->firstOrFail();

    // 200 subtotal - 15 discount + 5 tax
    expect($order->subtotal)->toBe('200.00')
        ->and($order->total)->toBe('190.00');
});

it('honours an explicit unit price over the catalogue price', function () {
    $product = Product::factory()->create(['selling_price' => 40]);

    $this->actingAs($this->clerk)
        ->post('/inventory/orders', [
            'customer_name' => 'Walk-in',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 30],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(Order::query()->firstOrFail()->total)->toBe('60.00');
});

it('prices a variant line from the variant', function () {
    $product = Product::factory()->variable()->create(['selling_price' => 40]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'selling_price' => 55,
    ]);

    $this->actingAs($this->clerk)
        ->post('/inventory/orders', [
            'customer_name' => 'Walk-in',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                ],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(Order::query()->firstOrFail()->total)->toBe('55.00');
});

it('refuses a variant belonging to another product', function () {
    $product = Product::factory()->create();
    $other = ProductVariant::factory()->create();

    $this->actingAs($this->clerk)
        ->post('/inventory/orders', [
            'customer_name' => 'Walk-in',
            'items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $other->id,
                    'quantity' => 1,
                ],
            ],
        ])
        ->assertSessionHasErrors('items.0.product_variant_id');
});

it('refuses a product that is not for sale', function () {
    $product = Product::factory()->create(['status' => ProductStatus::Archived]);

    $this->actingAs($this->clerk)
        ->post('/inventory/orders', [
            'customer_name' => 'Walk-in',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertSessionHasErrors('items.0.product_id');
});

it('takes an order beyond available stock, because a draft reserves nothing', function () {
    // Overselling is rejected at confirmation, not at intake: a shop can write
    // down an order it intends to restock for.
    $product = Product::factory()->create(['selling_price' => 5]);

    app(InventoryService::class)->record(
        new StockableUnit($product->id),
        StockMovementType::OpeningStock,
        2,
    );

    $this->actingAs($this->clerk)
        ->post('/inventory/orders', [
            'customer_name' => 'Walk-in',
            'items' => [['product_id' => $product->id, 'quantity' => 50]],
        ])
        ->assertSessionHasNoErrors();

    expect(Order::query()->firstOrFail()->items()->first()->quantity)->toBe(50);
});

it('edits a draft order and rebuilds its totals', function () {
    $product = Product::factory()->create(['selling_price' => 20]);
    $replacement = Product::factory()->create(['selling_price' => 12]);

    $this->actingAs($this->clerk)->post('/inventory/orders', [
        'customer_name' => 'Walk-in',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ]);

    $order = Order::query()->firstOrFail();

    $this->actingAs($this->clerk)
        ->put("/inventory/orders/{$order->id}", [
            'customer_name' => 'Walk-in',
            'items' => [['product_id' => $replacement->id, 'quantity' => 4]],
        ])
        ->assertSessionHasNoErrors();

    $order->refresh()->load('items');

    expect($order->items)->toHaveCount(1)
        ->and($order->items->first()->product_id)->toBe($replacement->id)
        ->and($order->total)->toBe('48.00');
});

it('sends a confirmed order back to its detail screen instead of the edit form', function () {
    $product = Product::factory()->create();

    app(InventoryService::class)->record(
        new StockableUnit($product->id),
        StockMovementType::OpeningStock,
        10,
    );

    $order = Order::factory()->create();
    $order->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 5,
        'line_total' => 5,
    ]);

    app(ConfirmOrderAction::class)->handle($order->refresh());

    $this->actingAs($this->clerk)
        ->get("/inventory/orders/{$order->id}/edit")
        ->assertRedirect("/inventory/orders/{$order->id}")
        ->assertSessionHas('error');
});

it('denies taking an order without orders.create', function () {
    $viewer = userWithPermissions([Permissions::ORDERS_VIEW]);

    $this->actingAs($viewer)->get('/inventory/orders/create')->assertForbidden();
});
