<?php

namespace Modules\Inventory\Enums;

/**
 * Inventory effects are bound to transitions, not to the status itself:
 *
 *  - pending   -> confirmed : reserves stock (quantity_reserved += qty)
 *  - confirmed -> completed : deducts on-hand and releases the reservation
 *  - confirmed -> cancelled : releases any remaining reservation
 *
 * See Modules\Inventory\Services\InventoryService for the mechanics.
 */
enum OrderStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Items/totals may only change while the order has no inventory impact.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Pending], true);
    }

    /**
     * True once the order holds a stock reservation.
     */
    public function holdsReservation(): bool
    {
        return in_array($this, [self::Confirmed, self::Processing], true);
    }

    /**
     * Statuses from which the order's lines may still be handed over to the
     * customer, which is what turns the reservation into an on-hand deduction.
     */
    public function isFulfillable(): bool
    {
        return $this->holdsReservation();
    }

    public function isCancellable(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending, self::Cancelled],
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Processing, self::Completed, self::Cancelled],
            self::Processing => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
