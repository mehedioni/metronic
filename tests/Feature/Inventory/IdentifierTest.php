<?php

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Inventory\Models\Customer;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductVariant;
use Modules\Inventory\Models\Supplier;

it('keys every domain table with an auto-incrementing integer', function () {
    $product = Product::factory()->create();
    $customer = Customer::factory()->create();
    $order = Order::factory()->create();

    expect($product->id)->toBeInt()
        ->and($customer->id)->toBeInt()
        ->and($order->id)->toBeInt()
        ->and($product->getKeyType())->toBe('int')
        ->and($product->getIncrementing())->toBeTrue();
});

it('hands out consecutive keys', function () {
    $first = Product::factory()->create();
    $second = Product::factory()->create();

    expect($second->id)->toBe($first->id + 1);
});

it('gives a product a public uuid alongside its integer key', function () {
    $product = Product::factory()->create();

    expect($product->id)->toBeInt()
        ->and($product->uuid)->toBeString()
        ->and(Str::isUuid($product->uuid))->toBeTrue();
});

it('generates the uuid even when model events are muted', function () {
    // Seeders run inside Model::withoutEvents(). A NOT NULL unique column that
    // depended on a "creating" listener would fail its insert there, which is
    // why the value is produced in the constructor instead.
    Product::withoutEvents(function (): void {
        Product::factory()->create(['name' => 'Silent insert']);
    });

    $product = Product::query()->where('name', 'Silent insert')->firstOrFail();

    expect(Str::isUuid($product->uuid))->toBeTrue();
});

it('keeps product uuids unique', function () {
    $taken = Product::factory()->create()->uuid;

    expect(fn () => Product::factory()->create(['uuid' => $taken]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('never changes a product uuid once stored', function () {
    $product = Product::factory()->create();
    $original = $product->uuid;

    $product->update(['name' => 'Renamed']);

    expect($product->refresh()->uuid)->toBe($original);
});

it('ignores a uuid supplied through a request payload', function () {
    // The public identifier is generated, so it is absent from the fillable
    // list and cannot be steered from outside.
    $product = Product::create([
        'name' => 'Fill guard',
        'slug' => 'fill-guard',
        'uuid' => '11111111-1111-1111-1111-111111111111',
    ]);

    expect($product->uuid)->not->toBe('11111111-1111-1111-1111-111111111111');
});

it('finds a product by its public uuid', function () {
    $product = Product::factory()->create();

    expect(Product::findByUuid($product->uuid)?->id)->toBe($product->id)
        ->and(Product::findByUuid((string) Str::uuid()))->toBeNull();
});

it('mirrors a numeric variant id into variant_key', function () {
    $product = Product::factory()->variable()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    $product->suppliers()->attach(
        Supplier::factory()->create()->id,
        ['product_variant_id' => $variant->id, 'lead_time_days' => 5],
    );

    $row = DB::table('product_supplier')->first();

    // The mirror is a string column so a composite unique index can rely on
    // it; '' stands for "the product itself", which NULL could not.
    expect($row->variant_key)->toBe((string) $variant->id);

    $product->suppliers()->attach(
        Supplier::factory()->create()->id,
        ['lead_time_days' => 3],
    );

    expect(DB::table('product_supplier')->orderByDesc('id')->value('variant_key'))
        ->toBe('');
});

it('resolves a route by integer key', function () {
    $viewer = superAdmin();
    $product = Product::factory()->create();

    $this->actingAs($viewer)
        ->get("/inventory/products/{$product->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('product.id', $product->id));
});

it('rejects a non-numeric id in a filter', function () {
    $this->actingAs(superAdmin())
        ->get('/inventory/products?category_id=not-a-key')
        ->assertSessionHasErrors('category_id');
});

it('rejects a zero or negative id in a filter', function () {
    $this->actingAs(superAdmin())
        ->get('/inventory/products?category_id=0')
        ->assertSessionHasErrors('category_id');
});
