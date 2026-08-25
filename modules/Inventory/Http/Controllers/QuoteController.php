<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Http\Requests\ListRequest;
use Modules\Inventory\Http\Requests\StoreOrderRequest;
use Modules\Inventory\Models\Customer;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Services\OrderService;
use Modules\Inventory\Support\OrderStatuses;

/**
 * Quotes are orders in the configured quote status — a draft, by default.
 *
 * They are the same record as an order, not a separate table: a quote that is
 * accepted moves on through the same lifecycle, keeping its number, its lines
 * and its history. This controller only ever narrows to that one status, so
 * relabelling it in config/orders.php renames the screen too.
 */
class QuoteController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(ListRequest $request): Response
    {
        $this->authorize('viewAny', Order::class);

        $status = OrderStatuses::quote();

        return Inertia::render('Inventory::Quotes/Index', [
            'quotes' => $this->orders->paginate([
                ...$request->filters(),
                // The screen is defined by the status, so a filter cannot
                // widen it to other orders.
                'status' => $status->id,
            ]),
            'filters' => $request->filters(),
            'status' => $status,
            'options' => $this->formOptions(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Order::class);

        return Inertia::render('Inventory::Quotes/Create', [
            'status' => OrderStatuses::quote(),
            'options' => $this->formOptions(),
        ]);
    }

    /**
     * A quote is stored in the quote status regardless of what the form sent,
     * which is what makes it a quote rather than an order.
     */
    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', Order::class);

        $order = $this->orders->create([
            ...$request->validated(),
            'status_id' => OrderStatuses::quote()->id,
        ], $request->user()->id);

        return redirect()
            ->route('inventory.orders.show', $order)
            ->with('success', 'Quote created.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'customers' => Customer::query()
                ->active()
                ->select(['id', 'code', 'name', 'email'])
                ->orderBy('name')
                ->get(),
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
