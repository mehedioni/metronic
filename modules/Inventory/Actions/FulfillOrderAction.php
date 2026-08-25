<?php

namespace Modules\Inventory\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Exceptions\AlreadyProcessedException;
use Modules\Inventory\Exceptions\InvalidStatusTransitionException;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\OrderItem;
use Modules\Inventory\Services\InventoryService;
use Modules\Inventory\Support\MovementContext;
use Modules\Inventory\Support\OrderStatuses;

/**
 * Hands order lines over to the customer: deducts on-hand stock for the
 * quantities being fulfilled and releases the matching reservation.
 *
 * This is the only outbound stock path for orders. Confirmation reserves
 * stock; fulfilment is what actually removes it.
 *
 * A partially fulfilled order moves to processing and can be fulfilled again;
 * a fully fulfilled one moves to completed.
 *
 * Idempotency: the order and each line's quantity_fulfilled are read under a
 * row lock and written in the same transaction as the movements, so a repeat
 * call with nothing left outstanding is rejected rather than deducting twice.
 */
class FulfillOrderAction
{
    public function __construct(private InventoryService $inventory) {}

    /**
     * @param  array<string, int>  $lines  order_item_id => quantity; empty
     *                                     fulfils every outstanding line in full
     */
    public function handle(Order $order, array $lines = [], ?int $userId = null): Order
    {
        return DB::transaction(function () use ($order, $lines, $userId): Order {
            $locked = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $completed = OrderStatuses::key('completed');

            if ($locked->status->is($completed)) {
                throw AlreadyProcessedException::for("Order {$locked->order_number}");
            }

            if (! $locked->status->isFulfillable()) {
                throw InvalidStatusTransitionException::between(
                    $locked->status->key,
                    $completed->key,
                );
            }

            $locked->load('items');
            $issued = 0;

            foreach ($locked->items as $item) {
                $quantity = $this->quantityFor($item, $lines);

                if ($quantity <= 0) {
                    continue;
                }

                $this->issue($locked, $item, $quantity, $userId);
                $issued += $quantity;
            }

            if ($issued === 0) {
                throw AlreadyProcessedException::for("Order {$locked->order_number}");
            }

            $this->advance($locked);

            return $locked->refresh();
        });
    }

    /**
     * How much of a line to fulfil now: the caller's figure clamped to what is
     * still outstanding, or all of it when the caller named no lines.
     *
     * @param  array<string, int>  $lines
     */
    private function quantityFor(OrderItem $item, array $lines): int
    {
        $outstanding = $item->outstandingQuantity();

        if ($lines === []) {
            return $outstanding;
        }

        return min($outstanding, max(0, (int) ($lines[$item->getKey()] ?? 0)));
    }

    /**
     * Deduct one line and record how much of it has now left the store.
     */
    private function issue(Order $order, OrderItem $item, int $quantity, ?int $userId): void
    {
        $this->inventory->issueForOrder(
            $item->unit(),
            $quantity,
            new MovementContext(
                reference: $order,
                reason: "Order {$order->order_number}",
                // The line's cost snapshot travels onto the ledger row, so the
                // audit trail says what leaving stock was worth.
                unitCost: $item->unit_cost === null ? null : (float) $item->unit_cost,
                userId: $userId,
            ),
        );

        $item->forceFill([
            'quantity_fulfilled' => $item->quantity_fulfilled + $quantity,
        ])->save();
    }

    /**
     * A partly fulfilled order sits in processing; a fully fulfilled one
     * completes.
     */
    private function advance(Order $order): void
    {
        $order->load('items');

        $target = $order->isFullyFulfilled()
            ? OrderStatuses::key('completed')
            : OrderStatuses::key('processing');

        if ($order->status->is($target) || ! $order->status->canTransitionTo($target)) {
            return;
        }

        $order->forceFill([
            'status_id' => $target->id,
            'completed_at' => $target->is('completed') ? Carbon::now() : null,
        ])->save();
    }
}
