<?php

namespace Modules\Inventory\Http\Controllers\Api;

use App\Core\BaseApiController;
use App\Core\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Modules\Inventory\Services\DashboardService;

class DashboardController extends BaseApiController
{
    public function __construct(private DashboardService $dashboard) {}

    public function index(): JsonResponse
    {
        $this->authorize(Permissions::DASHBOARD_VIEW);

        return $this->success($this->dashboard->statistics());
    }
}
