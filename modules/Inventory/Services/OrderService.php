<?php

namespace Modules\Inventory\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Exceptions\RestrictedDeletionException;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductVariant;
use Modules\Inventory\Support\DocumentNumberGenerator;

class OrderService
{
    public function __construct(private DocumentNumberGenerator $numbers) {}

    /**
     * @param  array{search?: string|null, status?: string|null, from?: string|null, to?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Order::query()
            ->with('createdBy:id,name')
            ->withCount('items')
            ->search($filters['search'] ?? null)
            ->between($filters['from'] ?? null, $filters['to'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
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
     * Once an order reserves or ships stock its lines are frozen; it can only
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
                'line_total' => round($unitPrice * $quantity, 2),
            ]);
        }
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
