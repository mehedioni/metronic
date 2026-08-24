<?php

namespace Modules\Inventory\Enums;

enum InboundReceiptStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Received = 'received';
    case Cancelled = 'cancelled';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Only a receipt in one of these statuses may still be edited or deleted;
     * a received receipt owns stock movements and must stay intact.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Pending], true);
    }

    /**
     * Statuses this receipt may transition into.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending, self::Received, self::Cancelled],
            self::Pending => [self::Received, self::Cancelled],
            self::Received => [self::Cancelled],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
