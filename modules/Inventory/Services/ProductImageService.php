<?php

namespace Modules\Inventory\Services;

use App\Core\Services\FileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductImage;
use Modules\Inventory\Models\ProductVariant;

/**
 * Product image lifecycle: add, reorder, promote, remove.
 *
 * Knows about products and ordering; knows nothing about disks or URLs, which
 * belong to FileStorageService. Every file removal is deferred until the
 * surrounding transaction commits, so a rollback can never leave a row
 * pointing at bytes that are already gone.
 */
class ProductImageService
{
    public function __construct(private FileStorageService $files) {}

    /**
     * Store uploads against a product, appending them after any it already has.
     *
     * The first image a product ever gets becomes its primary, because a
     * gallery with no primary has nothing to show in a list.
     *
     * @param  array<int, UploadedFile>  $uploads
     * @return array<int, ProductImage>
     */
    public function add(Product $product, array $uploads, ?ProductVariant $variant = null): array
    {
        if ($uploads === []) {
            return [];
        }

        $directory = $this->directoryFor($product);
        $stored = [];

        // Files are written before the transaction opens: a failed upload
        // should not roll back rows, and a rolled-back row only ever leaves an
        // orphaned file, which is the recoverable direction.
        foreach ($uploads as $upload) {
            $stored[] = $this->files->store($upload, $directory);
        }

        return DB::transaction(function () use ($product, $variant, $stored): array {
            $existing = $product->images()->count();
            $hasPrimary = $product->images()->primary()->exists();
            $created = [];

            foreach (array_values($stored) as $index => $file) {
                $created[] = $product->images()->create([
                    ...$file->toArray(),
                    'product_variant_id' => $variant?->getKey(),
                    'sort_order' => $existing + $index,
                    'is_primary' => ! $hasPrimary && $index === 0,
                ]);
            }

            return $created;
        });
    }

    /**
     * Replace one image with a new upload, keeping its place in the order.
     */
    public function replace(ProductImage $image, UploadedFile $upload): ProductImage
    {
        $file = $this->files->store($upload, $this->directoryFor($image->product));
        $previousPath = $image->path;
        $previousDisk = $image->disk;

        return DB::transaction(function () use ($image, $file, $previousPath, $previousDisk): ProductImage {
            $image->update($file->toArray());

            // The old bytes go only once this commit succeeds.
            $this->files->deleteAfterCommit($previousPath, $previousDisk);

            return $image->refresh();
        });
    }

    /**
     * Make one image the product's primary, demoting whichever held it.
     */
    public function makePrimary(ProductImage $image): ProductImage
    {
        return DB::transaction(function () use ($image): ProductImage {
            ProductImage::query()
                ->where('product_id', $image->product_id)
                ->whereKeyNot($image->getKey())
                ->update(['is_primary' => false]);

            $image->update(['is_primary' => true]);

            return $image->refresh();
        });
    }

    /**
     * Apply an explicit order, given image ids in the order they should show.
     *
     * Ids that do not belong to the product are ignored rather than trusted.
     *
     * @param  array<int, int>  $imageIds
     */
    public function reorder(Product $product, array $imageIds): void
    {
        $owned = $product->images()->pluck('id')->all();

        DB::transaction(function () use ($imageIds, $owned): void {
            $position = 0;

            foreach ($imageIds as $id) {
                if (! in_array((int) $id, $owned, true)) {
                    continue;
                }

                ProductImage::query()->whereKey($id)->update(['sort_order' => $position++]);
            }
        });
    }

    /**
     * Remove an image, promoting the next one when the primary is deleted so a
     * product with images always has one to show.
     */
    public function delete(ProductImage $image): void
    {
        DB::transaction(function () use ($image): void {
            $productId = $image->product_id;
            $wasPrimary = $image->is_primary;
            $path = $image->path;
            $disk = $image->disk;

            $image->delete();

            if ($wasPrimary) {
                $next = ProductImage::query()
                    ->where('product_id', $productId)
                    ->ordered()
                    ->first();

                $next?->update(['is_primary' => true]);
            }

            $this->files->deleteAfterCommit($path, $disk);
        });
    }

    /**
     * Remove every image for a product — used when the product itself goes.
     */
    public function deleteAll(Product $product): void
    {
        $images = $product->images()->get(['id', 'path', 'disk']);

        DB::transaction(function () use ($product, $images): void {
            $product->images()->delete();

            foreach ($images as $image) {
                $this->files->deleteAfterCommit($image->path, $image->disk);
            }
        });
    }

    /**
     * Images live under the product they belong to, so everything for one
     * product can be found — or removed — as a unit.
     */
    private function directoryFor(Product $product): string
    {
        return $this->files->path('products', $product->getKey(), 'images');
    }
}
