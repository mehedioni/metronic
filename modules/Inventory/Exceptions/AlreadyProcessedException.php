<?php

namespace Modules\Inventory\Exceptions;

/**
 * Raised when a document that has already moved stock is asked to move it
 * again — the guard that keeps receiving and dispatching idempotent.
 */
class AlreadyProcessedException extends InventoryException
{
    public static function for(string $document): self
    {
        return new self("{$document} has already been processed; stock was not changed again.");
    }
}
