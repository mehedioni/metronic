<?php

namespace Modules\Inventory\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Exceptions\InvalidStatusTransitionException;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Services\InventoryService;
use Modules\Inventory\Support\OrderStatuses;

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

            $confirmed = OrderStatuses::key('confirmed');

            if (! $locked->status->canTransitionTo($confirmed)) {
                throw InvalidStatusTransitionException::between(
                    $locked->status->key,
                    $confirmed->key,
                );
            }

            $locked->load('items');

            foreach ($locked->items as $item) {
                $this->inventory->reserve($item->unit(), $item->quantity);
            }

            $locked->forceFill([
                'status_id' => $confirmed->id,
                'confirmed_at' => Carbon::now(),
            ])->save();

            return $locked->refresh();
        });
    }
}
