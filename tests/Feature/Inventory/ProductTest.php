<?php

use App\Core\Support\Permissions;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductSupplier;
use Modules\Inventory\Models\Supplier;

beforeEach(function () {
    $this->manager = userWithPermissions([
        Permissions::PRODUCTS_VIEW,
        Permissions::PRODUCTS_CREATE,
        Permissions::PRODUCTS_UPDATE,
        Permissions::PRODUCTS_DELETE,
    ]);
});

it('creates a simple product', function () {
    $category = Category::factory()->create();

    $this->actingAs($this->manager)->post('/inventory/products', [
        'name' => 'Cotton T-Shirt',
        'category_id' => $category->id,
        'selling_price' => 19.99,
    ])->assertSessionHasNoErrors();

    expect(Product::query()->where('name', 'Cotton T-Shirt')->exists())->toBeTrue();
});

it('creates a variable product with variants', function () {
    $this->actingAs($this->manager)->post('/inventory/products', [
        'name' => 'Hoodie',
        'type' => 'variable',
        'variants' => [
            ['sku' => 'HOOD-S', 'name' => 'Small', 'options' => ['size' => 'S']],
            ['sku' => 'HOOD-M', 'name' => 'Medium', 'options' => ['size' => 'M']],
        ],
    ])->assertSessionHasNoErrors();

    expect(Product::query()->where('name', 'Hoodie')->firstOrFail()->variants)->toHaveCount(2);
});

it('rejects a variable product with no variants', function () {
    $this->actingAs($this->manager)
        ->post('/inventory/products', ['name' => 'Empty', 'type' => 'variable'])
        ->assertSessionHasErrors('variants');
});

it('links a product to multiple suppliers with their own terms', function () {
    $first = Supplier::factory()->create();
    $second = Supplier::factory()->create();

    $this->actingAs($this->manager)->post('/inventory/products', [
        'name' => 'Multi Sourced',
        'primary_supplier_id' => $first->id,
        'suppliers' => [
            ['supplier_id' => $first->id, 'unit_cost' => 5.5, 'is_preferred' => true, 'lead_time_days' => 3],
            ['supplier_id' => $second->id, 'unit_cost' => 6.25, 'minimum_order_quantity' => 10],
        ],
    ])->assertSessionHasNoErrors();

    $product = Product::query()->where('name', 'Multi Sourced')->firstOrFail();

    expect($product->suppliers)->toHaveCount(2)
        ->and(ProductSupplier::query()->where('product_id', $product->id)->where('is_preferred', true)->count())->toBe(1);
});

it('validates product input', function () {
    $this->actingAs($this->manager)
        ->post('/inventory/products', ['name' => '', 'selling_price' => -1])
        ->assertSessionHasErrors(['name', 'selling_price']);
});

it('rejects a duplicate sku', function () {
    Product::factory()->create(['sku' => 'TAKEN-1']);

    $this->actingAs($this->manager)
        ->post('/inventory/products', ['name' => 'Another', 'sku' => 'TAKEN-1'])
        ->assertSessionHasErrors('sku');
});

it('filters products by low stock', function () {
    $this->actingAs($this->manager)->get('/inventory/products?low_stock=1')->assertOk();
});
