<?php

namespace Modules\Inventory\Enums;

enum InboundSource: string
{
    case Supplier = 'supplier';
    case Purchase = 'purchase';
    case Manual = 'manual';
    case CustomerReturn = 'customer_return';
    case OpeningStock = 'opening_stock';
    case Transfer = 'transfer';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Sources that must reference a supplier.
     */
    public function requiresSupplier(): bool
    {
        return in_array($this, [self::Supplier, self::Purchase], true);
    }

    /**
     * Stock movement type produced when a receipt of this source is processed.
     */
    public function movementType(): StockMovementType
    {
        return match ($this) {
            self::Supplier, self::Purchase, self::Manual => StockMovementType::StockReceived,
            self::CustomerReturn => StockMovementType::CustomerReturn,
            self::OpeningStock => StockMovementType::OpeningStock,
            self::Transfer => StockMovementType::TransferIn,
        };
    }
}
