<?php

use App\Core\Support\Permissions;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Services\ProductService;

beforeEach(function () {
    $this->viewer = userWithPermissions([Permissions::PRODUCTS_VIEW]);

    Product::factory()->create(['name' => 'Alpha', 'sku' => 'SKU-A']);
    Product::factory()->create(['name' => 'Zulu', 'sku' => 'SKU-Z']);
    Product::factory()->create(['name' => 'Mike', 'sku' => 'SKU-M']);
});

it('sorts a list by an allowed column ascending', function () {
    $names = app(ProductService::class)
        ->paginate(['sort' => 'name', 'direction' => 'asc'])
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Alpha', 'Mike', 'Zulu']);
});

it('sorts a list by an allowed column descending', function () {
    $names = app(ProductService::class)
        ->paginate(['sort' => 'name', 'direction' => 'desc'])
        ->pluck('name')
        ->all();

    expect($names)->toBe(['Zulu', 'Mike', 'Alpha']);
});

it('falls back to the default order for a column that is not allowed', function () {
    $allowed = app(ProductService::class)
        ->paginate(['sort' => 'name', 'direction' => 'asc'])
        ->pluck('name')
        ->all();

    $rejected = app(ProductService::class)
        ->paginate(['sort' => 'cost_price); drop table products;--', 'direction' => 'asc'])
        ->pluck('name')
        ->all();

    // The fallback orders by created_at then id, so it is stable but not
    // alphabetical, and the injected string never reaches the database.
    expect($rejected)->not->toBe($allowed)
        ->and($rejected)->toEqualCanonicalizing($allowed)
        ->and($rejected)->toBe(
            app(ProductService::class)->paginate(['sort' => 'nonsense'])->pluck('name')->all(),
        )
        ->and(Product::query()->count())->toBe(3);
});

it('rejects a direction that is not asc or desc', function () {
    $this->actingAs($this->viewer)
        ->get('/inventory/products?sort=name&direction=sideways')
        ->assertSessionHasErrors('direction');
});

it('accepts a sort through the products screen', function () {
    $this->actingAs($this->viewer)
        ->get('/inventory/products?sort=name&direction=asc')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Inventory::Products/Index')
            ->where('products.data.0.name', 'Alpha'));
});
