<?php

namespace Modules\Inventory\Models;

use App\Core\BaseUuidModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['shipment_id', 'order_item_id', 'quantity'])]
class ShipmentItem extends BaseUuidModel
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
