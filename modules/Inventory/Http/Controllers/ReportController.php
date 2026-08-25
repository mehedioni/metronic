<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Http\Requests\ReportRequest;
use Modules\Inventory\Services\ReportService;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    /**
     * Daily sales, cost of goods sold, expenses and profit.
     *
     * The report is a read of orders, order lines and expenses — there is no
     * report table to authorize against, so the ability is checked as a plain
     * gate rather than through a model policy.
     */
    public function daily(ReportRequest $request): Response
    {
        $this->authorize('viewReports');

        $filters = $request->filters();

        return Inertia::render('Inventory::Reports/Daily', [
            'report' => $this->reports->daily($filters),
            'filters' => $filters,
            'expensesByCategory' => Inertia::defer(
                fn () => $this->reports->expensesByCategory($filters),
            ),
        ]);
    }
}
