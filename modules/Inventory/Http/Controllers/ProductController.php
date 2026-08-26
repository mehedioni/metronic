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
use Modules\Inventory\Services\ProductImageService;
use Modules\Inventory\Services\ProductService;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $products,
        private ProductImageService $images,
    ) {}

    public function index(ListRequest $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $statusCounts = Product::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $filters = array_merge([
            'per_page' => 25,
        ], $request->filters());

        return Inertia::render('Inventory::Products/Index', [
            'products' => $this->products->paginate($filters),
            'filters' => $filters,
            'options' => $this->formOptions(),
            'counts' => [
                'all' => (int) $statusCounts->sum(),
                'active' => (int) $statusCounts->get(ProductStatus::Active->value, 0),
                'inactive' => (int) $statusCounts->get(ProductStatus::Inactive->value, 0),
                'archived' => (int) $statusCounts->get(ProductStatus::Archived->value, 0),
            ],
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

        // Images need the product's id, so they are stored once it exists.
        // The same service the edit screen uses, so ordering and the primary
        // flag behave identically however a product was created.
        $this->images->add($product, $request->images());

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
                'images',
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
                'images',
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
