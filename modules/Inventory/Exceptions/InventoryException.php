<?php

namespace Modules\Inventory\Exceptions;

use RuntimeException;

/**
 * Base for domain rule violations raised by this module. Rendered as a 422 by
 * the Inventory exception handling in InventoryServiceProvider.
 */
abstract class InventoryException extends RuntimeException
{
    /**
     * Field name the message should be attached to when reported as a
     * validation error.
     */
    public function errorKey(): string
    {
        return 'inventory';
    }
}
