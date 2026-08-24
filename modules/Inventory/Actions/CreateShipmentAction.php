<?php

namespace Modules\Inventory\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Inventory\Enums\OrderStatus;
use Modules\Inventory\Enums\ShipmentStatus;
use Modules\Inventory\Exceptions\InvalidStatusTransitionException;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Shipment;
use Modules\Inventory\Support\DocumentNumberGenerator;

/**
 * Creates a pending shipment for an order. Creating a shipment has no stock
 * effect — stock leaves only when the shipment is dispatched.
 */
class CreateShipmentAction
{
    public function __construct(private DocumentNumberGenerator $numbers) {}

    /**
     * @param  array{carrier?: string|null, tracking_number?: string|null, notes?: string|null, items: array<int, array{order_item_id: string, quantity: int}>}  $data
     */
    public function handle(Order $order, array $data, ?int $userId = null): Shipment
    {
        if (! $order->status->holdsReservation() && $order->status !== OrderStatus::Shipped) {
            throw InvalidStatusTransitionException::between(
                $order->status->value,
                ShipmentStatus::Pending->value,
            );
        }

        return DB::transaction(function () use ($order, $data, $userId): Shipment {
            $shipment = Shipment::create([
                'shipment_number' => $this->numbers->generate(Shipment::class, 'shipment_number', 'shipment'),
                'order_id' => $order->getKey(),
                'status' => ShipmentStatus::Pending,
                'carrier' => $data['carrier'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($data['items'] as $line) {
                $shipment->items()->create([
                    'order_item_id' => $line['order_item_id'],
                    'quantity' => $line['quantity'],
                ]);
            }

            return $shipment->load('items');
        });
    }
}
