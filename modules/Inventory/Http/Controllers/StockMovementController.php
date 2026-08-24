<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Http\Requests\ListRequest;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Services\StockQueryService;

class StockMovementController extends Controller
{
    public function __construct(private StockQueryService $stock) {}

    public function index(ListRequest $request): Response
    {
        $this->authorize('viewAny', StockMovement::class);

        return Inertia::render('Inventory::Movements/Index', [
            'movements' => $this->stock->paginateMovements($request->filters()),
            'filters' => $request->filters(),
            'types' => StockMovementType::values(),
        ]);
    }
}
