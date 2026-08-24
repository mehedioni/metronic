<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Actions\CancelOrderAction;
use Modules\Inventory\Actions\ConfirmOrderAction;
use Modules\Inventory\Enums\OrderStatus;
use Modules\Inventory\Http\Requests\CancelRequest;
use Modules\Inventory\Http\Requests\ListRequest;
use Modules\Inventory\Http\Requests\StoreOrderRequest;
use Modules\Inventory\Http\Requests\UpdateOrderRequest;
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
                'items.product:id,name,sku',
                'items.variant:id,sku,name',
                'shipments:id,order_id,shipment_number,status,shipped_at',
                'createdBy:id,name',
            ]),
            'allowedTransitions' => array_map(
                fn (OrderStatus $status): string => $status->value,
                $order->status->allowedTransitions(),
            ),
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
            'products' => Product::query()
                ->active()
                ->select(['id', 'name', 'sku', 'selling_price', 'type'])
                ->with('variants:id,product_id,sku,name,selling_price')
                ->orderBy('name')
                ->get(),
        ];
    }
}
