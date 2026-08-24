<?php

namespace Modules\Inventory\Exceptions;

/**
 * Raised instead of deleting a record whose removal would orphan or
 * invalidate historical data. Callers should deactivate/archive instead.
 */
class RestrictedDeletionException extends InventoryException
{
    public static function because(string $subject, string $reason): self
    {
        return new self("{$subject} cannot be deleted because {$reason}. Deactivate it instead.");
    }
}
