<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Enums\ProductStatus;
use Modules\Inventory\Enums\ProductType;
use Modules\Inventory\Http\Requests\ListRequest;
use Modules\Inventory\Http\Requests\StoreProductRequest;
use Modules\Inventory\Http\Requests\UpdateProductRequest;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Services\ProductService;

class ProductController extends Controller
{
    public function __construct(private ProductService $products) {}

    public function index(ListRequest $request): Response
    {
        $this->authorize('viewAny', Product::class);

        return Inertia::render('Inventory::Products/Index', [
            'products' => $this->products->paginate($request->filters()),
            'filters' => $request->filters(),
            'options' => $this->formOptions(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('Inventory::Products/Create', [
            'options' => $this->formOptions(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->products->create($request->validated());

        return redirect()
            ->route('inventory.products.show', $product)
            ->with('success', 'Product created.');
    }

    public function show(Product $product): Response
    {
        $this->authorize('view', $product);

        return Inertia::render('Inventory::Products/Show', [
            'product' => $product->load([
                'category:id,name',
                'primarySupplier:id,company_name',
                'variants',
                'suppliers:id,company_name',
                'inventoryItems',
            ]),
            'options' => $this->formOptions(),
        ]);
    }

    public function edit(Product $product): Response
    {
        $this->authorize('update', $product);

        return Inertia::render('Inventory::Products/Edit', [
            'product' => $product->load([
                'category:id,name',
                'primarySupplier:id,company_name',
                'variants',
                'suppliers:id,company_name',
            ]),
            'options' => $this->formOptions(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->products->update($product, $request->validated());

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $this->products->delete($product);

        return redirect()
            ->route('inventory.products.index')
            ->with('success', 'Product deleted.');
    }

    /**
     * Reference data every product form needs.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => Category::query()->active()->select(['id', 'name'])->orderBy('name')->get(),
            'suppliers' => Supplier::query()->active()->select(['id', 'company_name'])->orderBy('company_name')->get(),
            'types' => ProductType::values(),
            'statuses' => ProductStatus::values(),
        ];
    }
}
