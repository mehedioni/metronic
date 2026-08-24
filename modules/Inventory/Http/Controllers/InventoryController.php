<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Actions\AdjustStockAction;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Http\Requests\AdjustStockRequest;
use Modules\Inventory\Http\Requests\ListRequest;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Services\StockPlannerService;
use Modules\Inventory\Services\StockQueryService;
use Modules\Inventory\Support\StockableUnit;

class InventoryController extends Controller
{
    public function __construct(private StockQueryService $stock) {}

    public function index(ListRequest $request): Response
    {
        $this->authorize('viewAny', InventoryItem::class);

        return Inertia::render('Inventory::Stock/Index', [
            'items' => $this->stock->paginateItems($request->filters()),
            'filters' => $request->filters(),
            'categories' => Category::query()->select(['id', 'name'])->orderBy('name')->get(),
            'movementTypes' => StockMovementType::manualValues(),
        ]);
    }

    /**
     * Reorder planning. Every figure is derived on read from the ledger, the
     * thresholds and the supplier links — see StockPlannerService.
     */
    public function planner(ListRequest $request, StockPlannerService $planner): Response
    {
        $this->authorize('viewAny', InventoryItem::class);

        return Inertia::render('Inventory::Stock/Planner', [
            'items' => $planner->paginate([
                ...$request->filters(),
                'reorder_within' => $request->integer('reorder_within') ?: null,
            ]),
            'filters' => $request->filters(),
            'summary' => Inertia::defer(fn () => $planner->summary()),
            'categories' => Category::query()->select(['id', 'name'])->orderBy('name')->get(),
        ]);
    }

    /**
     * Manual stock correction. Everything else that moves stock does so
     * through receiving or order fulfilment.
     */
    public function adjust(AdjustStockRequest $request, AdjustStockAction $adjust): RedirectResponse
    {
        $this->authorize('adjust', InventoryItem::class);

        $data = $request->validated();

        $adjust->handle(
            new StockableUnit($data['product_id'], $data['product_variant_id'] ?? null),
            StockMovementType::from($data['type']),
            (int) $data['quantity'],
            $data['reason'] ?? null,
            $request->user()->id,
        );

        return back()->with('success', 'Stock adjusted.');
    }
}
