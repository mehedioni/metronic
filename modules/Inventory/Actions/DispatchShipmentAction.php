<?php

namespace Modules\Inventory\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\OrderStatus;
use Modules\Inventory\Enums\ShipmentStatus;
use Modules\Inventory\Exceptions\AlreadyProcessedException;
use Modules\Inventory\Exceptions\InvalidStatusTransitionException;
use Modules\Inventory\Models\Shipment;
use Modules\Inventory\Services\InventoryService;
use Modules\Inventory\Support\MovementContext;

/**
 * Dispatches a shipment: deducts on-hand stock, releases the matching
 * reservation, advances the order.
 *
 * Idempotency: "shipped_at" is checked under a row lock and written in the
 * same transaction as the movements, so a second dispatch is rejected rather
 * than deducting twice.
 */
class DispatchShipmentAction
{
    public function __construct(private InventoryService $inventory) {}

    public function handle(Shipment $shipment, ?int $userId = null): Shipment
    {
        return DB::transaction(function () use ($shipment, $userId): Shipment {
            $locked = Shipment::query()
                ->whereKey($shipment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->hasDispatched()) {
                throw AlreadyProcessedException::for("Shipment {$locked->shipment_number}");
            }

            if (! $locked->status->canTransitionTo(ShipmentStatus::Shipped)) {
                throw InvalidStatusTransitionException::between(
                    $locked->status->value,
                    ShipmentStatus::Shipped->value,
                );
            }

            $locked->load(['items.orderItem', 'order.items']);

            foreach ($locked->items as $line) {
                $orderItem = $line->orderItem;

                $this->inventory->issueForShipment(
                    $orderItem->unit(),
                    $line->quantity,
                    new MovementContext(
                        reference: $locked,
                        reason: "Shipment {$locked->shipment_number}",
                        userId: $userId,
                    ),
                );

                $orderItem->forceFill([
                    'quantity_shipped' => $orderItem->quantity_shipped + $line->quantity,
                ])->save();
            }

            $locked->forceFill([
                'status' => ShipmentStatus::Shipped,
                'shipped_at' => Carbon::now(),
            ])->save();

            $this->advanceOrder($locked);

            return $locked->refresh();
        });
    }

    /**
     * A partially shipped order moves to processing; a fully shipped one to
     * shipped.
     */
    private function advanceOrder(Shipment $shipment): void
    {
        $order = $shipment->order->refresh()->load('items');

        $target = $order->isFullyShipped() ? OrderStatus::Shipped : OrderStatus::Processing;

        if ($order->status === $target || ! $order->status->canTransitionTo($target)) {
            return;
        }

        $order->forceFill(['status' => $target])->save();
    }
}
