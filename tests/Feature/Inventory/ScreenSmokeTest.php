<?php

use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\Customer;
use Modules\Inventory\Models\InboundReceipt;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Supplier;

/**
 * Every screen renders for a user who may see it, and names the Inertia
 * component the frontend resolver has to find. A renamed or missing page
 * component fails here rather than in the browser.
 */

/**
 * Absolute path of the Vue file behind an Inertia page name, mirroring the
 * resolver in resources/js/app.ts.
 */
function pagePath(string $name): string
{
    if (! str_contains($name, '::')) {
        return resource_path("js/pages/{$name}.vue");
    }

    [$module, $page] = explode('::', $name, 2);

    return base_path("modules/{$module}/Resources/js/pages/{$page}.vue");
}

/**
 * Every page name passed to Inertia::render anywhere in the application.
 *
 * @return array<int, string>
 */
function renderedPageNames(): array
{
    $names = [];

    foreach (['modules', 'app'] as $directory) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(base_path($directory))
        );

        foreach ($files as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all(
                "/Inertia::render\(\s*'([^']+)'/",
                (string) file_get_contents($file->getPathname()),
                $matches,
            );

            $names = [...$names, ...$matches[1]];
        }
    }

    return array_values(array_unique($names));
}
beforeEach(function () {
    $this->admin = superAdmin();
});

it('renders every list screen', function (string $uri, string $component) {
    $this->actingAs($this->admin)
        ->get($uri)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    ['/dashboard', 'Inventory::Dashboard'],
    ['/inventory/products', 'Inventory::Products/Index'],
    ['/inventory/products/create', 'Inventory::Products/Create'],
    ['/inventory/categories', 'Inventory::Categories/Index'],
    ['/inventory/categories/create', 'Inventory::Categories/Create'],
    ['/inventory/suppliers', 'Inventory::Suppliers/Index'],
    ['/inventory/customers', 'Inventory::Customers/Index'],
    ['/inventory/orders', 'Inventory::Orders/Index'],
    ['/inventory/stock', 'Inventory::Stock/Index'],
    ['/inventory/stock/planner', 'Inventory::Stock/Planner'],
    ['/inventory/movements', 'Inventory::Movements/Index'],
    ['/inventory/inbound', 'Inventory::Inbound/Index'],
    ['/access/users', 'Access::Users/Index'],
    ['/access/roles', 'Access::Roles/Index'],
    ['/access/permissions', 'Access::Permissions/Index'],
]);

it('renders every detail screen', function () {
    $product = Product::factory()->create();
    $category = Category::factory()->create();
    $supplier = Supplier::factory()->create();
    $customer = Customer::factory()->create();
    $order = Order::factory()->create();
    $receipt = InboundReceipt::factory()->create();

    $screens = [
        "/inventory/products/{$product->id}" => 'Inventory::Products/Show',
        "/inventory/products/{$product->id}/edit" => 'Inventory::Products/Edit',
        "/inventory/categories/{$category->id}" => 'Inventory::Categories/Show',
        "/inventory/categories/{$category->id}/edit" => 'Inventory::Categories/Edit',
        "/inventory/suppliers/{$supplier->id}" => 'Inventory::Suppliers/Show',
        "/inventory/customers/{$customer->id}" => 'Inventory::Customers/Show',
        "/inventory/orders/{$order->id}" => 'Inventory::Orders/Show',
        "/inventory/inbound/{$receipt->id}" => 'Inventory::Inbound/Show',
    ];

    foreach ($screens as $uri => $component) {
        $this->actingAs($this->admin)
            ->get($uri)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component($component));
    }
});

it('has a Vue file behind every page name a controller renders', function () {
    // Inertia's own page-existence check cannot resolve "Module::Page" names
    // (see config/inertia.php), so the mapping is asserted here instead: a
    // renamed or unwritten page component fails the suite, not the browser.
    $missing = [];

    foreach (renderedPageNames() as $name) {
        if (! file_exists(pagePath($name))) {
            $missing[] = $name;
        }
    }

    expect($missing)->toBe([]);
});

it('shares the configured store name with every page', function () {
    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('app.name', config('app.name')));
});
