<?php

namespace Modules\Inventory\Http\Controllers\Api;

use App\Core\BaseApiController;
use Illuminate\Http\JsonResponse;
use Modules\Inventory\Http\Requests\ListRequest;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Services\ProductService;

/**
 * Read-only product endpoints. Writes go through the Inertia controllers so
 * the business rules live in exactly one place.
 */
class ProductController extends BaseApiController
{
    public function __construct(private ProductService $products) {}

    public function index(ListRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        return $this->success($this->products->paginate($request->filters()));
    }

    public function show(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        return $this->success(
            $product->load(['category:id,name', 'variants', 'inventoryItems']),
        );
    }
}
