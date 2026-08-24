<?php

namespace Modules\Inventory\Enums;

/**
 * Every on-hand stock change is written to stock_movements with one of these
 * types. Inbound types add stock, outbound types remove it — see direction().
 */
enum StockMovementType: string
{
    case OpeningStock = 'opening_stock';
    case StockReceived = 'stock_received';
    case CustomerReturn = 'customer_return';
    case AdjustmentIncrease = 'adjustment_increase';
    case TransferIn = 'transfer_in';

    case AdjustmentDecrease = 'adjustment_decrease';
    case OrderOut = 'order_out';
    case Damage = 'damage';
    case ManualRemoval = 'manual_removal';
    case TransferOut = 'transfer_out';
    case ReceiptReversal = 'receipt_reversal';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * +1 for movements that increase on-hand stock, -1 for movements that
     * decrease it.
     */
    public function direction(): int
    {
        return match ($this) {
            self::OpeningStock,
            self::StockReceived,
            self::CustomerReturn,
            self::AdjustmentIncrease,
            self::TransferIn => 1,
            default => -1,
        };
    }

    public function isInbound(): bool
    {
        return $this->direction() === 1;
    }

    /**
     * Types a user may pick when manually adjusting stock. System-driven types
     * (order fulfilment, receipt reversal) are excluded.
     *
     * @return array<int, string>
     */
    public static function manualValues(): array
    {
        return [
            self::OpeningStock->value,
            self::AdjustmentIncrease->value,
            self::CustomerReturn->value,
            self::TransferIn->value,
            self::AdjustmentDecrease->value,
            self::Damage->value,
            self::ManualRemoval->value,
            self::TransferOut->value,
        ];
    }
}
