<?php

use App\Core\Support\Permissions;
use Modules\Inventory\Models\Customer;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Product;

beforeEach(function () {
    $this->manager = userWithPermissions([
        Permissions::CUSTOMERS_VIEW,
        Permissions::CUSTOMERS_CREATE,
        Permissions::CUSTOMERS_UPDATE,
        Permissions::CUSTOMERS_DELETE,
    ]);
});

it('creates a customer and generates a code', function () {
    $this->actingAs($this->manager)->post('/inventory/customers', [
        'name' => 'Emma Chen',
        'email' => 'emma@example.test',
        'country' => 'CA',
    ])->assertSessionHasNoErrors();

    $customer = Customer::query()->where('email', 'emma@example.test')->firstOrFail();

    expect($customer->code)->toStartWith('CUS-')
        ->and($customer->status->value)->toBe('active');
});

it('keeps a caller-supplied customer code', function () {
    $this->actingAs($this->manager)->post('/inventory/customers', [
        'code' => 'VIP-1',
        'name' => 'Grace Lopez',
    ])->assertSessionHasNoErrors();

    expect(Customer::query()->where('code', 'VIP-1')->exists())->toBeTrue();
});

it('rejects a duplicate customer email', function () {
    Customer::factory()->create(['email' => 'dup@example.test']);

    $this->actingAs($this->manager)
        ->post('/inventory/customers', ['name' => 'Other', 'email' => 'dup@example.test'])
        ->assertSessionHasErrors('email');
});

it('validates customer input', function () {
    $this->actingAs($this->manager)
        ->post('/inventory/customers', ['name' => '', 'email' => 'nope'])
        ->assertSessionHasErrors(['name', 'email']);
});

it('toggles customer status', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($this->manager)
        ->patch("/inventory/customers/{$customer->id}/status")
        ->assertSessionHasNoErrors();

    expect($customer->refresh()->status->value)->toBe('inactive');
});

it('refuses to delete a customer who has orders', function () {
    $customer = Customer::factory()->create();
    Order::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($this->manager)
        ->delete("/inventory/customers/{$customer->id}")
        ->assertSessionHasErrors();

    expect(Customer::query()->whereKey($customer->id)->exists())->toBeTrue();
});

it('deletes a customer with no orders', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($this->manager)
        ->delete("/inventory/customers/{$customer->id}")
        ->assertSessionHasNoErrors();

    expect(Customer::query()->whereKey($customer->id)->exists())->toBeFalse();
});

it('lists customers with spend aggregates summed from their orders', function () {
    $customer = Customer::factory()->create(['name' => 'Repeat Buyer']);

    Order::factory()->create(['customer_id' => $customer->id, 'total' => 100]);
    Order::factory()->create(['customer_id' => $customer->id, 'total' => 300]);
    // Cancelled orders never count towards spend.
    Order::factory()->create([
        'customer_id' => $customer->id,
        'total' => 999,
        'status' => 'cancelled',
    ]);

    $this->actingAs($this->manager)
        ->get('/inventory/customers')
        ->assertInertia(fn ($page) => $page
            ->component('Inventory::Customers/Index')
            ->where('customers.data.0.orders_count', 2)
            ->where('customers.data.0.total_spent', 400));
});

it('denies a user without the customers permission', function () {
    $outsider = userWithPermissions([]);

    $this->actingAs($outsider)->get('/inventory/customers')->assertForbidden();
});

it('snapshots the customer contact details onto a new order', function () {
    $customer = Customer::factory()->create([
        'name' => 'Sophia Patel',
        'email' => 'sophia@example.test',
        'phone' => '+15550001111',
    ]);

    $creator = userWithPermissions([
        Permissions::ORDERS_VIEW,
        Permissions::ORDERS_CREATE,
        Permissions::PRODUCTS_VIEW,
    ]);

    $product = Product::factory()->create(['selling_price' => 25]);

    $this->actingAs($creator)->post('/inventory/orders', [
        'customer_id' => $customer->id,
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ])->assertSessionHasNoErrors();

    $order = Order::query()->where('customer_id', $customer->id)->firstOrFail();

    expect($order->customer_name)->toBe('Sophia Patel')
        ->and($order->customer_email)->toBe('sophia@example.test')
        ->and($order->customer_phone)->toBe('+15550001111');
});
