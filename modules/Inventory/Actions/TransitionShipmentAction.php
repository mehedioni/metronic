<?php

namespace Modules\Inventory\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\ShipmentStatus;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Exceptions\InvalidStatusTransitionException;
use Modules\Inventory\Models\Shipment;
use Modules\Inventory\Services\InventoryService;
use Modules\Inventory\Support\MovementContext;

/**
 * Advances a shipment through its remaining statuses.
 *
 * Dispatching is deliberately not handled here — it has an inventory effect
 * and lives in DispatchShipmentAction. Cancelling a shipment that already
 * left the warehouse returns its stock and re-reserves it for the order.
 */
class TransitionShipmentAction
{
    public function __construct(private InventoryService $inventory) {}

    public function handle(Shipment $shipment, ShipmentStatus $target, ?int $userId = null): Shipment
    {
        if ($target === ShipmentStatus::Shipped) {
            throw new InvalidStatusTransitionException(
                'Use the dispatch endpoint to ship a shipment so stock is deducted.',
            );
        }

        return DB::transaction(function () use ($shipment, $target, $userId): Shipment {
            $locked = Shipment::query()
                ->whereKey($shipment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->status->canTransitionTo($target)) {
                throw InvalidStatusTransitionException::between($locked->status->value, $target->value);
            }

            if ($target === ShipmentStatus::Cancelled && $locked->hasDispatched()) {
                $this->returnStock($locked, $userId);
            }

            $locked->forceFill([
                'status' => $target,
                'delivered_at' => $target === ShipmentStatus::Delivered ? Carbon::now() : $locked->delivered_at,
                'cancelled_at' => $target === ShipmentStatus::Cancelled ? Carbon::now() : $locked->cancelled_at,
                'shipped_at' => $target === ShipmentStatus::Cancelled ? null : $locked->shipped_at,
            ])->save();

            return $locked->refresh();
        });
    }

    /**
     * Put dispatched units back on the shelf and, when the order still holds a
     * reservation, promise them to that order again.
     */
    private function returnStock(Shipment $shipment, ?int $userId): void
    {
        $shipment->load(['items.orderItem', 'order']);
        $orderHoldsReservation = $shipment->order->status->holdsReservation();

        foreach ($shipment->items as $line) {
            $orderItem = $line->orderItem;

            $this->inventory->record(
                $orderItem->unit(),
                StockMovementType::CustomerReturn,
                $line->quantity,
                new MovementContext(
                    reference: $shipment,
                    reason: "Cancelled shipment {$shipment->shipment_number}",
                    userId: $userId,
                ),
            );

            $orderItem->forceFill([
                'quantity_shipped' => max(0, $orderItem->quantity_shipped - $line->quantity),
            ])->save();

            if ($orderHoldsReservation) {
                $this->inventory->reserve($orderItem->unit(), $line->quantity);
            }
        }
    }
}
