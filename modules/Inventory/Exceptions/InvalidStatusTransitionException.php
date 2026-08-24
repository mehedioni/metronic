<?php

namespace Modules\Inventory\Exceptions;

class InvalidStatusTransitionException extends InventoryException
{
    public static function between(string $from, string $to): self
    {
        return new self("Cannot change status from \"{$from}\" to \"{$to}\".");
    }

    public function errorKey(): string
    {
        return 'status';
    }
}
