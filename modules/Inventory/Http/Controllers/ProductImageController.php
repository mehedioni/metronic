<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Inventory\Http\Requests\ReorderProductImagesRequest;
use Modules\Inventory\Http\Requests\StoreProductImageRequest;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductImage;
use Modules\Inventory\Services\ProductImageService;

/**
 * Product images. The controller validates and authorises; where the bytes go
 * is ProductImageService's business, and which disk holds them is
 * FileStorageService's.
 */
class ProductImageController extends Controller
{
    public function __construct(private ProductImageService $images) {}

    public function store(StoreProductImageRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $variantId = $request->validated('product_variant_id');

        $variant = $variantId
            ? $product->variants()->whereKey($variantId)->firstOrFail()
            : null;

        $added = $this->images->add($product, $request->images(), $variant);

        return back()->with('success', count($added) === 1
            ? 'Image uploaded.'
            : count($added).' images uploaded.');
    }

    /**
     * Nested binding is checked explicitly: an image id from another product
     * must not be reachable through this product's URL.
     */
    public function makePrimary(Product $product, ProductImage $image): RedirectResponse
    {
        $this->authorize('update', $product);
        $this->assertBelongsTo($product, $image);

        $this->images->makePrimary($image);

        return back()->with('success', 'Primary image updated.');
    }

    public function reorder(ReorderProductImagesRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->images->reorder($product, $request->imageIds());

        return back()->with('success', 'Image order saved.');
    }

    public function destroy(Product $product, ProductImage $image): RedirectResponse
    {
        $this->authorize('update', $product);
        $this->assertBelongsTo($product, $image);

        $this->images->delete($image);

        return back()->with('success', 'Image removed.');
    }

    private function assertBelongsTo(Product $product, ProductImage $image): void
    {
        abort_unless($image->product_id === $product->getKey(), 404);
    }
}
