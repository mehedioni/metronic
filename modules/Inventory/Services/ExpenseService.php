<?php

namespace Modules\Inventory\Services;

use App\Core\Support\QuerySorter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Inventory\Models\Expense;

class ExpenseService
{
    private const SORTABLE = [
        'spent_on' => 'spent_on',
        'category' => 'category',
        'amount' => 'amount',
        'created_at' => 'created_at',
    ];

    /**
     * @param  array{search?: string|null, category?: string|null, supplier_id?: string|null, from?: string|null, to?: string|null, sort?: string|null, direction?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, Expense>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Expense::query()
            ->with(['supplier:id,company_name', 'createdBy:id,name'])
            ->search($filters['search'] ?? null)
            ->between($filters['from'] ?? null, $filters['to'] ?? null)
            ->when($filters['category'] ?? null, fn (Builder $q, string $category) => $q->where('category', $category))
            ->when($filters['supplier_id'] ?? null, fn (Builder $q, string $supplier) => $q->where('supplier_id', $supplier))
            ->tap(fn (Builder $q) => QuerySorter::apply(
                $q,
                $filters['sort'] ?? null,
                $filters['direction'] ?? null,
                self::SORTABLE,
                'spent_on',
            ))
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * Totals for the cards above the list, over the same filters as the list.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $query = Expense::query()
            ->search($filters['search'] ?? null)
            ->between($filters['from'] ?? null, $filters['to'] ?? null)
            ->when($filters['category'] ?? null, fn (Builder $q, string $category) => $q->where('category', $category));

        return [
            'count' => (clone $query)->count(),
            'total' => round((float) (clone $query)->sum('amount'), 2),
            'by_category' => (clone $query)
                ->selectRaw('category, sum(amount) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->pluck('total', 'category')
                ->map(fn ($total): float => round((float) $total, 2))
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?int $userId = null): Expense
    {
        return Expense::create([...$data, 'created_by' => $userId]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Expense $expense, array $data): Expense
    {
        $expense->update($data);

        return $expense->refresh();
    }

    /**
     * Expenses carry no downstream records, so unlike a supplier or a customer
     * there is nothing to protect — a mistyped one is simply removed.
     */
    public function delete(Expense $expense): void
    {
        $expense->delete();
    }
}
