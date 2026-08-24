<?php

namespace Modules\Inventory\Http\Controllers\Api;

use App\Core\BaseApiController;
use Illuminate\Http\JsonResponse;
use Modules\Inventory\Http\Requests\ListRequest;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Services\StockQueryService;

class StockController extends BaseApiController
{
    public function __construct(private StockQueryService $stock) {}

    public function items(ListRequest $request): JsonResponse
    {
        $this->authorize('viewAny', InventoryItem::class);

        return $this->success($this->stock->paginateItems($request->filters()));
    }

    public function movements(ListRequest $request): JsonResponse
    {
        $this->authorize('viewAny', StockMovement::class);

        return $this->success($this->stock->paginateMovements($request->filters()));
    }
}
