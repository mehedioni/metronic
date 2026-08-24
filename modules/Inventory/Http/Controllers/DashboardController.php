<?php

namespace Modules\Inventory\Http\Controllers;

use App\Core\Support\Permissions;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard) {}

    public function index(): Response
    {
        $this->authorize(Permissions::DASHBOARD_VIEW);

        return Inertia::render('Inventory::Dashboard', [
            'statistics' => Inertia::defer(fn () => $this->dashboard->statistics()),
        ]);
    }
}
