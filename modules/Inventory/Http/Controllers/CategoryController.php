<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Enums\RecordStatus;
use Modules\Inventory\Http\Requests\ListRequest;
use Modules\Inventory\Http\Requests\StoreCategoryRequest;
use Modules\Inventory\Http\Requests\UpdateCategoryRequest;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Services\CategoryService;

class CategoryController extends Controller
{
    public function __construct(private CategoryService $categories) {}

    public function index(ListRequest $request): Response
    {
        $this->authorize('viewAny', Category::class);

        return Inertia::render('Inventory::Categories/Index', [
            'categories' => $this->categories->paginate($request->filters()),
            'filters' => $request->filters(),
            'statuses' => RecordStatus::values(),
            'parents' => Category::query()->select(['id', 'name'])->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', Category::class);

        $this->categories->create($request->validated());

        return back()->with('success', 'Category created.');
    }

    public function show(Category $category): Response
    {
        $this->authorize('view', $category);

        return Inertia::render('Inventory::Categories/Show', [
            'category' => $category->load(['parent:id,name', 'children:id,name,parent_id'])->loadCount('products'),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $this->categories->update($category, $request->validated());

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $this->categories->delete($category);

        return back()->with('success', 'Category deleted.');
    }
}
