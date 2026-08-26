<?php

namespace Modules\Inventory\Services;

use App\Core\Support\QuerySorter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Modules\Inventory\Enums\RecordStatus;
use Modules\Inventory\Exceptions\RestrictedDeletionException;
use Modules\Inventory\Models\Customer;
use Modules\Inventory\Support\DocumentNumberGenerator;
use Modules\Inventory\Support\OrderStatuses;

class CustomerService
{
    /**
     * Aggregate keys are sortable because paginate() selects them as columns.
     */
    private const SORTABLE = [
        'name' => 'name',
        'code' => 'code',
        'country' => 'country',
        'status' => 'status',
        'orders_count' => 'orders_count',
        'total_spent' => 'total_spent',
        'last_order_at' => 'last_order_at',
        'created_at' => 'created_at',
    ];

    public function __construct(private DocumentNumberGenerator $numbers) {}

    /**
     * The list screen needs order count, lifetime spend, average order value
     * and the last order date per row. All four are summed from the orders
     * table rather than stored, so they can never drift from it.
     *
     * @param  array{search?: string|null, status?: string|null, country?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, Customer>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Customer::query()
            ->withCount(['billableOrders as orders_count'])
            ->withSum(['billableOrders as total_spent'], 'total')
            ->withMax(['billableOrders as last_order_at'], 'created_at')
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['country'] ?? null, fn (Builder $query, string $country) => $query->where('country', $country))
            ->tap(fn (Builder $query) => QuerySorter::apply(
                $query,
                $filters['sort'] ?? null,
                $filters['direction'] ?? null,
                self::SORTABLE,
            ))
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Customer
    {
        return Customer::create([
            ...$this->attributes($data),
            'code' => $data['code'] ?? $this->numbers->generate(Customer::class, 'code', 'customer'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($this->attributes($data));

        return $customer->refresh();
    }

    /**
     * The record's own columns. The photo arrives as a file and is stored by
     * AvatarService, so it never belongs in a mass assignment.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return collect($data)->except(['avatar', 'remove_avatar'])->all();
    }

    public function setStatus(Customer $customer, RecordStatus $status): Customer
    {
        $customer->update(['status' => $status]);

        return $customer->refresh();
    }

    /**
     * Customers with orders are never removed — those orders have to keep
     * resolving to a customer. They are deactivated instead.
     */
    public function delete(Customer $customer): void
    {
        if ($customer->orders()->exists()) {
            throw RestrictedDeletionException::because(
                "Customer \"{$customer->name}\"",
                'they have order history that must stay valid',
            );
        }

        $customer->delete();
    }

    /**
     * Everything the customer detail screen shows beyond the record itself.
     *
     * @return array<string, mixed>
     */
    public function history(Customer $customer): array
    {
        $billable = $customer->billableOrders();

        $ordersCount = (clone $billable)->count();
        $totalSpent = (float) (clone $billable)->sum('total');

        return [
            'orders_count' => $ordersCount,
            'total_spent' => round($totalSpent, 2),
            'average_order_value' => $ordersCount > 0 ? round($totalSpent / $ordersCount, 2) : 0.0,
            'last_order_at' => (clone $billable)->max('created_at'),
            'cancelled_orders_count' => $customer->orders()
                ->whereIn('status_id', OrderStatuses::voidIds())
                ->count(),
            'recent_orders' => $customer->orders()
                ->withCount('items')
                ->latest()
                ->limit(10)
                ->get(['id', 'order_number', 'status', 'total', 'created_at']),
        ];
    }
}
