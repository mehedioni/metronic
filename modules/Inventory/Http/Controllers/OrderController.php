<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Actions\CancelOrderAction;
use Modules\Inventory\Actions\ConfirmOrderAction;
use Modules\Inventory\Actions\FulfillOrderAction;
use Modules\Inventory\Enums\OrderStatus;
use Modules\Inventory\Http\Requests\CancelRequest;
use Modules\Inventory\Http\Requests\FulfillOrderRequest;
use Modules\Inventory\Http\Requests\ListRequest;
use Modules\Inventory\Http\Requests\StoreOrderRequest;
use Modules\Inventory\Http\Requests\UpdateOrderRequest;
use Modules\Inventory\Models\Customer;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Services\OrderService;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(ListRequest $request): Response
    {
        $this->authorize('viewAny', Order::class);

        return Inertia::render('Inventory::Orders/Index', [
            'orders' => $this->orders->paginate($request->filters()),
            'filters' => $request->filters(),
            'counts' => [
                'all' => Order::count(),
                'in_transit' => Order::whereIn('status', ['confirmed', 'processing'])->count(),
                'delivered' => Order::where('status', 'completed')->count(),
                'returns' => Order::where('status', 'draft')->count(),
                'canceled' => Order::where('status', 'cancelled')->count(),
            ],
            'options' => $this->formOptions(),
        ]);
    }

    public function create(ListRequest $request): Response
    {
        $this->authorize('create', Order::class);

        return Inertia::render('Inventory::Orders/Create', [
            'orders' => $this->orders->paginate($request->filters()),
            'filters' => $request->filters(),
            'counts' => [
                'all' => Order::count(),
                'in_transit' => Order::whereIn('status', ['confirmed', 'processing'])->count(),
                'delivered' => Order::where('status', 'completed')->count(),
                'returns' => Order::where('status', 'draft')->count(),
                'canceled' => Order::where('status', 'cancelled')->count(),
            ],
            'options' => $this->formOptions(),
        ]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', Order::class);

        $order = $this->orders->create($request->validated(), $request->user()->id);

        return redirect()
            ->route('inventory.orders.show', $order)
            ->with('success', 'Order created.');
    }

    public function show(Order $order): Response
    {
        $this->authorize('view', $order);

        return Inertia::render('Inventory::Orders/Show', [
            'order' => $order->load([
                'customer:id,code,name,email,phone,city,country',
                'items.product:id,name,sku',
                'items.variant:id,sku,name',
                'createdBy:id,name',
            ]),
            'allowedTransitions' => array_map(
                fn (OrderStatus $status): string => $status->value,
                $order->status->allowedTransitions(),
            ),
            'options' => $this->formOptions(),
        ]);
    }

    /**
     * Lines and totals are only editable while the order has no inventory
     * impact, so a confirmed order is sent back to its detail screen rather
     * than opened in a form it could not save.
     */
    public function edit(Order $order): Response|RedirectResponse
    {
        $this->authorize('update', $order);

        if (! $order->status->isEditable()) {
            return redirect()
                ->route('inventory.orders.show', $order)
                ->with('error', "Order {$order->order_number} is {$order->status->value} and can no longer be edited.");
        }

        return Inertia::render('Inventory::Orders/Edit', [
            'order' => $order->load([
                'customer:id,code,name,email,phone',
                'items.product:id,name,sku',
                'items.variant:id,sku,name',
            ]),
            'options' => $this->formOptions(),
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('update', $order);

        $this->orders->update($order, $request->validated());

        return back()->with('success', 'Order updated.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        $this->authorize('delete', $order);

        $this->orders->delete($order);

        return redirect()
            ->route('inventory.orders.index')
            ->with('success', 'Order deleted.');
    }

    /**
     * Confirm the order, which reserves stock for every line.
     */
    public function confirm(Order $order, ConfirmOrderAction $confirm): RedirectResponse
    {
        $this->authorize('confirm', $order);

        $confirm->handle($order);

        return back()->with('success', 'Order confirmed and stock reserved.');
    }

    /**
     * Fulfil the order: deduct on-hand stock for the quantities handed over and
     * release their reservation. This is the only outbound stock path for
     * orders. Sending no lines fulfils everything still outstanding.
     */
    public function fulfill(FulfillOrderRequest $request, Order $order, FulfillOrderAction $fulfill): RedirectResponse
    {
        $this->authorize('fulfill', $order);

        $updated = $fulfill->handle($order, $request->lines(), $request->user()->id);

        return back()->with('success', $updated->status === OrderStatus::Completed
            ? 'Order fulfilled and stock deducted.'
            : 'Order partially fulfilled and stock deducted.');
    }

    public function cancel(CancelRequest $request, Order $order, CancelOrderAction $cancel): RedirectResponse
    {
        $this->authorize('cancel', $order);

        $cancel->handle($order, $request->validated()['reason'] ?? null, $request->user()->id);

        return back()->with('success', 'Order cancelled and stock released.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'statuses' => OrderStatus::values(),
            'customers' => Customer::query()
                ->active()
                ->select(['id', 'code', 'name', 'email'])
                ->orderBy('name')
                ->get(),
            // Stock comes along so the form can show what is available to
            // promise per unit. Confirming would reject an oversell anyway;
            // seeing it while typing beats finding out at confirmation.
            'products' => Product::query()
                ->active()
                ->select(['id', 'name', 'sku', 'selling_price', 'type'])
                ->with([
                    'variants:id,product_id,sku,name,selling_price',
                    'inventoryItems:id,product_id,product_variant_id,quantity_on_hand,quantity_reserved',
                ])
                ->orderBy('name')
                ->get(),
        ];
    }
}
