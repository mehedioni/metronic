<?php

namespace Modules\Inventory\Services;

use App\Core\Support\QuerySorter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Exceptions\RestrictedDeletionException;
use Modules\Inventory\Models\Customer;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductVariant;
use Modules\Inventory\Support\DocumentNumberGenerator;

class OrderService
{
    private const SORTABLE = [
        'order_number' => 'order_number',
        'customer_name' => 'customer_name',
        'status' => 'status_id',
        'total' => 'total',
        'created_at' => 'created_at',
    ];

    public function __construct(private DocumentNumberGenerator $numbers) {}

    /**
     * @param  array{search?: string|null, status?: int|string|null, without_status?: int|string|null, customer_id?: int|null, from?: string|null, to?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Order::query()
            ->with([
                'createdBy:id,name',
                // Enough of the customer record for the detail panel to show
                // who the order is for, beside the snapshot the order keeps.
                'customer:id,code,name,email,phone,city,country,status',
            ])
            ->withCount('items')
            ->search($filters['search'] ?? null)
            ->between($filters['from'] ?? null, $filters['to'] ?? null)
            // Accepts a configured status id or key; the shipping-era aliases
            // (in_transit, delivered, returns) named a lifecycle this system
            // no longer has.
            ->withStatus($filters['status'] ?? null)
            ->withoutStatus($filters['without_status'] ?? null)
            ->when($filters['customer_id'] ?? null, fn ($query, $customerId) => $query->where('customer_id', $customerId))
            ->tap(fn ($query) => QuerySorter::apply(
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
    public function create(array $data, ?int $userId = null): Order
    {
        return DB::transaction(function () use ($data, $userId): Order {
            $order = Order::create([
                ...collect($data)->except('items')->all(),
                ...$this->contactSnapshot($data),
                'order_number' => $data['order_number']
                    ?? $this->numbers->generate(Order::class, 'order_number', 'order'),
                'created_by' => $userId,
            ]);

            $this->replaceItems($order, $data['items'] ?? []);
            $order->recalculateTotals();

            return $order->refresh()->load('items');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Order $order, array $data): Order
    {
        $this->assertEditable($order);

        return DB::transaction(function () use ($order, $data): Order {
            $order->update(collect($data)->except('items')->all());

            if (array_key_exists('items', $data)) {
                $this->replaceItems($order, $data['items']);
            }

            $order->recalculateTotals();

            return $order->refresh()->load('items');
        });
    }

    public function delete(Order $order): void
    {
        $this->assertEditable($order);

        $order->delete();
    }

    /**
     * Contact details for a new order. When it names a customer, their current
     * details are copied onto the order so it still reads correctly after the
     * customer record changes; anything the caller sent explicitly wins.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function contactSnapshot(array $data): array
    {
        if (empty($data['customer_id'])) {
            return [];
        }

        $customer = Customer::query()->findOrFail($data['customer_id']);

        return collect($customer->orderSnapshot())
            ->reject(fn (mixed $value, string $key): bool => filled($data[$key] ?? null))
            ->all();
    }

    /**
     * Once an order reserves or fulfils stock its lines are frozen; it can only
     * be cancelled from then on.
     */
    private function assertEditable(Order $order): void
    {
        if (! $order->status->isEditable()) {
            throw RestrictedDeletionException::because(
                "Order \"{$order->order_number}\"",
                'it is '.$order->status->value.' and has an inventory impact',
            );
        }
    }

    /**
     * Rebuild the order lines, pricing each from the variant or product when
     * the caller did not send an explicit unit price.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function replaceItems(Order $order, array $items): void
    {
        $order->items()->delete();

        foreach ($items as $item) {
            $unitPrice = $item['unit_price'] ?? $this->resolveUnitPrice($item);
            $quantity = (int) $item['quantity'];

            $order->items()->create([
                'product_id' => $item['product_id'],
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'unit_cost' => $this->resolveUnitCost($item),
                'line_total' => round($unitPrice * $quantity, 2),
            ]);
        }
    }

    /**
     * What these units cost the store, captured at intake.
     *
     * Never resolved at report time: the product's cost price is current, and
     * reading it later would silently restate the margin on every past order
     * whenever a supplier changes their prices. Null stays null — an unknown
     * cost has to report as unknown, not as free.
     *
     * @param  array<string, mixed>  $item
     */
    private function resolveUnitCost(array $item): ?float
    {
        if (array_key_exists('unit_cost', $item) && $item['unit_cost'] !== null) {
            return (float) $item['unit_cost'];
        }

        if (! empty($item['product_variant_id'])) {
            $variant = ProductVariant::query()->find($item['product_variant_id']);

            if ($variant?->cost_price !== null) {
                return (float) $variant->cost_price;
            }
        }

        $cost = Product::query()->find($item['product_id'])?->cost_price;

        return $cost === null ? null : (float) $cost;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveUnitPrice(array $item): float
    {
        if (! empty($item['product_variant_id'])) {
            $variant = ProductVariant::query()->findOrFail($item['product_variant_id']);

            if ($variant->selling_price !== null) {
                return (float) $variant->selling_price;
            }
        }

        return (float) (Product::query()->findOrFail($item['product_id'])->selling_price ?? 0);
    }
}
