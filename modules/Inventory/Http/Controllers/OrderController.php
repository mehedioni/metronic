<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Actions\CancelOrderAction;
use Modules\Inventory\Actions\ConfirmOrderAction;
use Modules\Inventory\Actions\FulfillOrderAction;
use Modules\Inventory\Http\Requests\CancelRequest;
use Modules\Inventory\Http\Requests\FulfillOrderRequest;
use Modules\Inventory\Http\Requests\ListRequest;
use Modules\Inventory\Http\Requests\StoreOrderRequest;
use Modules\Inventory\Http\Requests\UpdateOrderRequest;
use Modules\Inventory\Models\Customer;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Services\OrderService;
use Modules\Inventory\Support\OrderStatus;
use Modules\Inventory\Support\OrderStatuses;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(ListRequest $request): Response
    {
        $this->authorize('viewAny', Order::class);

        return Inertia::render('Inventory::Orders/Index', [
            'orders' => $this->orders->paginate($this->listFilters($request->filters())),
            'filters' => $request->filters(),
            'counts' => $this->statusCounts(),
            // Tabs cover the statuses this list actually shows, so there is no
            // Quote tab that would always read zero.
            'listStatuses' => $this->listStatuses(),
            'options' => $this->formOptions(),
        ]);
    }

    public function create(ListRequest $request): Response
    {
        $this->authorize('create', Order::class);

        return Inertia::render('Inventory::Orders/Create', [
            'orders' => $this->orders->paginate($this->listFilters($request->filters())),
            'filters' => $request->filters(),
            'counts' => $this->statusCounts(),
            'listStatuses' => $this->listStatuses(),
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
                fn (OrderStatus $status): string => $status->key,
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
                ->with('error', "Order {$order->order_number} is {$order->status->label} and can no longer be edited.");
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

        return back()->with('success', $updated->status->is('completed')
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
     * Filters for the orders list: whatever was asked for, minus quotes.
     *
     * A quote is an order in the configured quote status, and it has its own
     * screen — showing it here as well would double-count the same record in
     * two lists.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function listFilters(array $filters): array
    {
        return [...$filters, 'without_status' => OrderStatuses::quote()->id];
    }

    /**
     * The statuses this list can show — everything but the quote status.
     *
     * @return array<int, OrderStatus>
     */
    private function listStatuses(): array
    {
        $quote = OrderStatuses::quote();

        return array_values(array_filter(
            OrderStatuses::all(),
            fn (OrderStatus $status): bool => ! $status->is($quote),
        ));
    }

    /**
     * One count per configured status, keyed by id, plus the total.
     *
     * Driven by the configuration rather than a hardcoded set of tabs, so
     * adding a status in config/orders.php gives it a tab and a count without
     * touching this controller.
     *
     * @return array<string, mixed>
     */
    private function statusCounts(): array
    {
        $byStatus = Order::query()
            ->withoutStatus(OrderStatuses::quote())
            ->select('status_id', DB::raw('count(*) as aggregate'))
            ->groupBy('status_id')
            ->pluck('aggregate', 'status_id');

        $counts = ['all' => (int) $byStatus->sum()];

        foreach ($this->listStatuses() as $status) {
            $counts[(string) $status->id] = (int) ($byStatus[$status->id] ?? 0);
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            // The whole configured lifecycle for filters, and the subset a
            // form may set for the status field.
            'statuses' => OrderStatuses::all(),
            'assignableStatuses' => OrderStatuses::assignable(),
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
