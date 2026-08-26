<?php

namespace Modules\Inventory\Http\Controllers;

use App\Core\Services\AvatarService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Enums\RecordStatus;
use Modules\Inventory\Http\Requests\ListRequest;
use Modules\Inventory\Http\Requests\StoreCustomerRequest;
use Modules\Inventory\Http\Requests\UpdateCustomerRequest;
use Modules\Inventory\Models\Customer;
use Modules\Inventory\Services\CustomerService;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customers,
        private AvatarService $avatars,
    ) {}

    public function index(ListRequest $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        return Inertia::render('Inventory::Customers/Index', [
            'customers' => $this->customers->paginate($request->filters()),
            'filters' => $request->filters(),
            'statuses' => RecordStatus::values(),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $customer = $this->customers->create($request->validated());

        // The photo needs the customer's id, so it is stored once it exists.
        $this->avatars->sync($customer, $request->file('avatar'));

        return redirect()
            ->route('inventory.customers.show', $customer)
            ->with('success', 'Customer created.');
    }

    public function show(Customer $customer): Response
    {
        $this->authorize('view', $customer);

        return Inertia::render('Inventory::Customers/Show', [
            'customer' => $customer,
            'history' => Inertia::defer(fn () => $this->customers->history($customer)),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $this->customers->update($customer, $request->validated());
        $this->avatars->sync($customer, $request->file('avatar'), $request->boolean('remove_avatar'));

        return back()->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $this->customers->delete($customer);

        return redirect()
            ->route('inventory.customers.index')
            ->with('success', 'Customer deleted.');
    }

    /**
     * Flip a customer between active and inactive without touching history.
     */
    public function toggleStatus(Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $this->customers->setStatus(
            $customer,
            $customer->isActive() ? RecordStatus::Inactive : RecordStatus::Active,
        );

        return back()->with('success', 'Customer status updated.');
    }
}
