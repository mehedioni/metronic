<?php

namespace Modules\Inventory\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Exceptions\InsufficientStockException;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Support\MovementContext;
use Modules\Inventory\Support\StockableUnit;

/**
 * The single write path for stock.
 *
 * Every method that changes a quantity runs inside a database transaction and
 * takes a row lock on the inventory_items row first, so two concurrent
 * requests for the same unit serialise instead of interleaving a stale read.
 *
 * On-hand changes always produce a stock_movements row. Reservations do not:
 * they move quantity_reserved only and leave on-hand (and therefore the
 * ledger) untouched.
 */
class InventoryService
{
    /**
     * Apply a stock movement and return the ledger row it produced.
     *
     * $quantity is always a positive magnitude; the direction comes from the
     * movement type.
     */
    public function record(
        StockableUnit $unit,
        StockMovementType $type,
        int $quantity,
        ?MovementContext $context = null,
    ): StockMovement {
        $this->assertPositive($quantity);

        $context ??= new MovementContext;

        return DB::transaction(function () use ($unit, $type, $quantity, $context): StockMovement {
            $item = $this->lockedItemFor($unit);

            $delta = $type->direction() * $quantity;
            $before = $item->quantity_on_hand;
            $after = $before + $delta;

            if ($after < 0 && ! config('inventory.allow_negative_stock')) {
                throw InsufficientStockException::forUnit($this->label($unit), $quantity, $before);
            }

            $item->forceFill(['quantity_on_hand' => $after])->save();

            return StockMovement::create([
                'product_id' => $unit->productId,
                'product_variant_id' => $unit->productVariantId,
                'type' => $type,
                'quantity' => $delta,
                'quantity_before' => $before,
                'quantity_after' => $after,
                ...$context->toAttributes(),
            ]);
        });
    }

    /**
     * Reserve stock for a confirmed order. Rejected when it would promise more
     * than is available, unless overselling is explicitly enabled.
     */
    public function reserve(StockableUnit $unit, int $quantity): InventoryItem
    {
        $this->assertPositive($quantity);

        return DB::transaction(function () use ($unit, $quantity): InventoryItem {
            $item = $this->lockedItemFor($unit);

            if ($quantity > $item->availableQuantity() && ! config('inventory.allow_overselling')) {
                throw InsufficientStockException::forUnit(
                    $this->label($unit),
                    $quantity,
                    $item->availableQuantity(),
                );
            }

            $item->forceFill([
                'quantity_reserved' => $item->quantity_reserved + $quantity,
            ])->save();

            return $item;
        });
    }

    /**
     * Release a reservation without moving on-hand stock (order cancelled, or
     * the reserved units have just been handed over to the customer).
     *
     * Clamped at zero so a double release can never drive the counter
     * negative.
     */
    public function release(StockableUnit $unit, int $quantity): InventoryItem
    {
        $this->assertPositive($quantity);

        return DB::transaction(function () use ($unit, $quantity): InventoryItem {
            $item = $this->lockedItemFor($unit);

            $item->forceFill([
                'quantity_reserved' => max(0, $item->quantity_reserved - $quantity),
            ])->save();

            return $item;
        });
    }

    /**
     * Deduct on-hand stock for a fulfilled order line and release the matching
     * reservation in the same transaction.
     */
    public function issueForOrder(
        StockableUnit $unit,
        int $quantity,
        MovementContext $context,
    ): StockMovement {
        return DB::transaction(function () use ($unit, $quantity, $context): StockMovement {
            $movement = $this->record($unit, StockMovementType::OrderOut, $quantity, $context);

            $this->release($unit, $quantity);

            return $movement;
        });
    }

    public function availableQuantity(StockableUnit $unit): int
    {
        return $this->itemFor($unit)->availableQuantity();
    }

    public function onHandQuantity(StockableUnit $unit): int
    {
        return $this->itemFor($unit)->quantity_on_hand;
    }

    /**
     * Get (or lazily create) the inventory row for a unit, without locking.
     */
    public function itemFor(StockableUnit $unit): InventoryItem
    {
        return InventoryItem::forUnit($unit)->first() ?? $this->createItemFor($unit);
    }

    /**
     * Fetch the inventory row for update, creating it first if this unit has
     * never held stock.
     */
    private function lockedItemFor(StockableUnit $unit): InventoryItem
    {
        $item = InventoryItem::forUnit($unit)->lockForUpdate()->first();

        if ($item) {
            return $item;
        }

        $this->createItemFor($unit);

        return InventoryItem::forUnit($unit)->lockForUpdate()->firstOrFail();
    }

    /**
     * Create the row, tolerating a concurrent creator: the composite unique
     * index is the arbiter, and the loser simply re-reads the winner's row.
     */
    private function createItemFor(StockableUnit $unit): InventoryItem
    {
        try {
            return InventoryItem::create([
                'product_id' => $unit->productId,
                'product_variant_id' => $unit->productVariantId,
            ]);
        } catch (QueryException $exception) {
            $existing = InventoryItem::forUnit($unit)->first();

            if (! $existing) {
                throw $exception;
            }

            return $existing;
        }
    }

    private function assertPositive(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Stock quantity must be greater than zero.');
        }
    }

    private function label(StockableUnit $unit): string
    {
        return $unit->productVariantId
            ? "variant {$unit->productVariantId}"
            : "product {$unit->productId}";
    }
}
