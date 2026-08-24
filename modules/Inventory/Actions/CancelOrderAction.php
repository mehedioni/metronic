<?php

namespace Modules\Inventory\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\OrderStatus;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Exceptions\InvalidStatusTransitionException;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Services\InventoryService;
use Modules\Inventory\Support\MovementContext;

/**
 * Cancels an order and unwinds whatever inventory effect it still holds:
 * outstanding reservations are released, and quantities already shipped are
 * returned to stock as a customer return movement.
 */
class CancelOrderAction
{
    public function __construct(private InventoryService $inventory) {}

    public function handle(Order $order, ?string $reason = null, ?int $userId = null): Order
    {
        return DB::transaction(function () use ($order, $reason, $userId): Order {
            $locked = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->status->isCancellable()) {
                throw InvalidStatusTransitionException::between(
                    $locked->status->value,
                    OrderStatus::Cancelled->value,
                );
            }

            $locked->load('items');
            $heldReservation = $locked->status->holdsReservation();

            foreach ($locked->items as $item) {
                if ($heldReservation && $item->outstandingQuantity() > 0) {
                    $this->inventory->release($item->unit(), $item->outstandingQuantity());
                }

                if ($item->quantity_shipped > 0) {
                    $this->inventory->record(
                        $item->unit(),
                        StockMovementType::CustomerReturn,
                        $item->quantity_shipped,
                        new MovementContext(
                            reference: $locked,
                            reason: $reason ?? "Cancelled order {$locked->order_number}",
                            userId: $userId,
                        ),
                    );

                    $item->forceFill(['quantity_shipped' => 0])->save();
                }
            }

            $locked->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => Carbon::now(),
                'notes' => $reason ?? $locked->notes,
            ])->save();

            return $locked->refresh();
        });
    }
}
