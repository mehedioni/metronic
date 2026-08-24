<?php

namespace Modules\Inventory\Exceptions;

class InsufficientStockException extends InventoryException
{
    public static function forUnit(string $label, int $requested, int $available): self
    {
        return new self(
            "Insufficient stock for {$label}: requested {$requested}, available {$available}.",
        );
    }

    public function errorKey(): string
    {
        return 'quantity';
    }
}
