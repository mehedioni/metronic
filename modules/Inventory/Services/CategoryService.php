<?php

namespace Modules\Inventory\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Inventory\Exceptions\CircularCategoryException;
use Modules\Inventory\Exceptions\RestrictedDeletionException;
use Modules\Inventory\Models\Category;

class CategoryService
{
    /**
     * @param  array{search?: string|null, status?: string|null, parent_id?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, Category>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Category::query()
            ->with('parent:id,name')
            ->withCount('products')
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['parent_id'] ?? null, fn ($query, $parent) => $query->where('parent_id', $parent))
            ->latest()
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Category
    {
        return Category::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data): Category
    {
        $this->assertParentIsNotCircular($category, $data['parent_id'] ?? null);

        $category->update($data);

        return $category->refresh();
    }

    /**
     * Soft delete, refused while the category still owns products or children
     * whose relationships would be left dangling.
     */
    public function delete(Category $category): void
    {
        if ($category->products()->exists()) {
            throw RestrictedDeletionException::because(
                "Category \"{$category->name}\"",
                'it still has products assigned',
            );
        }

        if ($category->children()->exists()) {
            throw RestrictedDeletionException::because(
                "Category \"{$category->name}\"",
                'it still has child categories',
            );
        }

        $category->delete();
    }

    private function assertParentIsNotCircular(Category $category, ?string $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if (in_array($parentId, $category->load('children.children')->descendantIds(), true)) {
            throw CircularCategoryException::make();
        }
    }
}
