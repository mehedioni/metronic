<?php

namespace Modules\Inventory\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\OrderStatus;
use Modules\Inventory\Exceptions\InvalidStatusTransitionException;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Services\InventoryService;

/**
 * Confirms an order and reserves stock for every line.
 *
 * The reservation is what prevents overselling: it is taken once, guarded by
 * the pending -> confirmed transition, so a repeated confirm is rejected
 * instead of reserving twice.
 */
class ConfirmOrderAction
{
    public function __construct(private InventoryService $inventory) {}

    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $locked = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->status->canTransitionTo(OrderStatus::Confirmed)) {
                throw InvalidStatusTransitionException::between(
                    $locked->status->value,
                    OrderStatus::Confirmed->value,
                );
            }

            $locked->load('items');

            foreach ($locked->items as $item) {
                $this->inventory->reserve($item->unit(), $item->quantity);
            }

            $locked->forceFill([
                'status' => OrderStatus::Confirmed,
                'confirmed_at' => Carbon::now(),
            ])->save();

            return $locked->refresh();
        });
    }
}
