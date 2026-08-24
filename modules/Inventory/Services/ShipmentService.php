<?php

namespace Modules\Inventory\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Inventory\Exceptions\RestrictedDeletionException;
use Modules\Inventory\Models\Shipment;

class ShipmentService
{
    /**
     * @param  array{search?: string|null, status?: string|null, order_id?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, Shipment>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Shipment::query()
            ->with(['order:id,order_number,customer_name', 'createdBy:id,name'])
            ->withCount('items')
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['order_id'] ?? null, fn ($query, $order) => $query->where('order_id', $order))
            ->latest()
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Shipment $shipment, array $data): Shipment
    {
        $shipment->update(collect($data)->only(['carrier', 'tracking_number', 'notes'])->all());

        return $shipment->refresh();
    }

    /**
     * Only an undispatched shipment can be removed; once stock has left the
     * warehouse the record has to stay for the audit trail.
     */
    public function delete(Shipment $shipment): void
    {
        if ($shipment->hasDispatched()) {
            throw RestrictedDeletionException::because(
                "Shipment \"{$shipment->shipment_number}\"",
                'it has already dispatched stock',
            );
        }

        $shipment->delete();
    }
}
