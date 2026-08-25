<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Inventory\Database\Factories\InboundReceiptItemFactory;
use Modules\Inventory\Support\StockableUnit;

#[Fillable([
    'inbound_receipt_id', 'product_id', 'product_variant_id', 'quantity',
    'unit_cost', 'supplier_sku', 'notes',
])]
#[UseFactory(InboundReceiptItemFactory::class)]
class InboundReceiptItem extends Model
{
    /** @use HasFactory<InboundReceiptItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(InboundReceipt::class, 'inbound_receipt_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function unit(): StockableUnit
    {
        return new StockableUnit($this->product_id, $this->product_variant_id);
    }
}
