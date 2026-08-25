<?php

use App\Core\Support\Permissions;
use Modules\Inventory\Actions\ConfirmOrderAction;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Services\InventoryService;
use Modules\Inventory\Support\OrderStatuses;
use Modules\Inventory\Support\StockableUnit;

beforeEach(function () {
    $this->clerk = userWithPermissions([
        Permissions::ORDERS_VIEW,
        Permissions::ORDERS_CREATE,
        Permissions::PRODUCTS_VIEW,
        Permissions::CUSTOMERS_VIEW,
    ]);

    OrderStatuses::flush();
});

it('creates a quote in the draft status', function () {
    $product = Product::factory()->create(['selling_price' => 40]);

    $this->actingAs($this->clerk)->post('/inventory/quotes', [
        'customer_name' => 'Emma Chen',
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ])->assertSessionHasNoErrors();

    $quote = Order::query()->firstOrFail();

    expect($quote->status->key)->toBe('draft')
        ->and($quote->status->id)->toBe(OrderStatuses::quote()->id)
        ->and($quote->total)->toBe('80.00');
});

it('stores a quote as draft even when the form asks for another status', function () {
    $product = Product::factory()->create(['selling_price' => 10]);

    $this->actingAs($this->clerk)->post('/inventory/quotes', [
        'customer_name' => 'Walk-in',
        // A quote is defined by its status, so the endpoint overrides this.
        'status_id' => OrderStatuses::key('pending')->id,
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertSessionHasNoErrors();

    expect(Order::query()->firstOrFail()->status->key)->toBe('draft');
});

it('lists only quotes, never other orders', function () {
    Order::factory()->draft()->create(['order_number' => 'ORD-QUOTE']);
    Order::factory()->create(['order_number' => 'ORD-PENDING']);
    Order::factory()->confirmed()->create(['order_number' => 'ORD-CONFIRMED']);

    $this->actingAs($this->clerk)
        ->get('/inventory/quotes')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Inventory::Quotes/Index')
            ->has('quotes.data', 1)
            ->where('quotes.data.0.order_number', 'ORD-QUOTE')
            ->where('status.key', 'draft'));
});

it('cannot be widened past the quote status by a filter', function () {
    Order::factory()->draft()->create();
    Order::factory()->confirmed()->create();

    $this->actingAs($this->clerk)
        ->get('/inventory/quotes?status='.OrderStatuses::key('confirmed')->id)
        ->assertInertia(fn ($page) => $page->has('quotes.data', 1));
});

it('follows the configured label, so renaming the status renames the screen', function () {
    config()->set('orders.statuses.0.label', 'Estimate');
    OrderStatuses::flush();

    $this->actingAs($this->clerk)
        ->get('/inventory/quotes')
        ->assertInertia(fn ($page) => $page->where('status.label', 'Estimate'));
});

it('keeps its number and lines when it is confirmed, rather than being copied', function () {
    $product = Product::factory()->create(['selling_price' => 25]);

    app(InventoryService::class)->record(
        new StockableUnit($product->id),
        StockMovementType::OpeningStock,
        10,
    );

    $this->actingAs($this->clerk)->post('/inventory/quotes', [
        'customer_name' => 'Emma Chen',
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ]);

    $quote = Order::query()->firstOrFail();
    $number = $quote->order_number;

    // draft -> pending -> confirmed, the configured path.
    $quote->setStatus('pending');
    $quote->save();
    app(ConfirmOrderAction::class)->handle($quote->refresh());

    $confirmed = Order::query()->firstOrFail();

    expect(Order::query()->count())->toBe(1)
        ->and($confirmed->order_number)->toBe($number)
        ->and($confirmed->status->key)->toBe('confirmed')
        ->and($confirmed->items)->toHaveCount(1);
});

it('keeps quotes out of the order list', function () {
    Order::factory()->draft()->create(['order_number' => 'ORD-QUOTE']);
    Order::factory()->create(['order_number' => 'ORD-PENDING']);

    $this->actingAs($this->clerk)
        ->get('/inventory/orders')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Inventory::Orders/Index')
            // The quote lives on its own screen; showing it here as well would
            // count the same record in two lists.
            ->has('orders.data', 1)
            ->where('orders.data.0.order_number', 'ORD-PENDING'));
});

it('leaves the quote status out of the order list tabs and counts', function () {
    Order::factory()->draft()->create();
    Order::factory()->create();

    $quoteId = (string) OrderStatuses::quote()->id;

    $this->actingAs($this->clerk)
        ->get('/inventory/orders')
        ->assertInertia(function ($page) use ($quoteId) {
            $props = $page->toArray()['props'];

            expect(array_column($props['listStatuses'], 'key'))->not->toContain('draft')
                ->and($props['counts'])->not->toHaveKey($quoteId)
                // The total counts only what the list shows.
                ->and($props['counts']['all'])->toBe(1);
        });
});

it('serves the quote form', function () {
    $this->actingAs($this->clerk)
        ->get('/inventory/quotes/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Inventory::Quotes/Create')
            ->where('status.key', 'draft')
            ->has('options.products'));
});

it('denies creating a quote without orders.create', function () {
    $this->actingAs(userWithPermissions([Permissions::ORDERS_VIEW]))
        ->get('/inventory/quotes/create')
        ->assertForbidden();
});
