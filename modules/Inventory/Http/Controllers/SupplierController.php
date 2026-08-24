<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Enums\RecordStatus;
use Modules\Inventory\Http\Requests\ListRequest;
use Modules\Inventory\Http\Requests\StoreSupplierRequest;
use Modules\Inventory\Http\Requests\UpdateSupplierRequest;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Services\SupplierService;

class SupplierController extends Controller
{
    public function __construct(private SupplierService $suppliers) {}

    public function index(ListRequest $request): Response
    {
        $this->authorize('viewAny', Supplier::class);

        return Inertia::render('Inventory::Suppliers/Index', [
            'suppliers' => $this->suppliers->paginate($request->filters()),
            'filters' => $request->filters(),
            'statuses' => RecordStatus::values(),
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        $this->suppliers->create($request->validated());

        return back()->with('success', 'Supplier created.');
    }

    public function show(Supplier $supplier): Response
    {
        $this->authorize('view', $supplier);

        return Inertia::render('Inventory::Suppliers/Show', [
            'supplier' => $supplier,
            'history' => Inertia::defer(fn () => $this->suppliers->history($supplier)),
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $this->suppliers->update($supplier, $request->validated());

        return back()->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        $this->suppliers->delete($supplier);

        return back()->with('success', 'Supplier deleted.');
    }

    /**
     * Flip a supplier between active and inactive without touching history.
     */
    public function toggleStatus(Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $this->suppliers->setStatus(
            $supplier,
            $supplier->isActive() ? RecordStatus::Inactive : RecordStatus::Active,
        );

        return back()->with('success', 'Supplier status updated.');
    }
}
