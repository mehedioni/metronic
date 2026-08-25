<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Enums\ExpenseCategory;
use Modules\Inventory\Http\Requests\ListRequest;
use Modules\Inventory\Http\Requests\StoreExpenseRequest;
use Modules\Inventory\Http\Requests\UpdateExpenseRequest;
use Modules\Inventory\Models\Expense;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Services\ExpenseService;

class ExpenseController extends Controller
{
    public function __construct(private ExpenseService $expenses) {}

    public function index(ListRequest $request): Response
    {
        $this->authorize('viewAny', Expense::class);

        $filters = $request->filters();

        return Inertia::render('Inventory::Expenses/Index', [
            'expenses' => $this->expenses->paginate($filters),
            'filters' => $filters,
            'summary' => Inertia::defer(fn () => $this->expenses->summary($filters)),
            'categories' => ExpenseCategory::values(),
            'suppliers' => Supplier::query()
                ->active()
                ->select(['id', 'company_name'])
                ->orderBy('company_name')
                ->get(),
        ]);
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $this->authorize('create', Expense::class);

        $this->expenses->create($request->validated(), $request->user()->id);

        return back()->with('success', 'Expense recorded.');
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        $this->expenses->update($expense, $request->validated());

        return back()->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $this->expenses->delete($expense);

        return back()->with('success', 'Expense deleted.');
    }
}
