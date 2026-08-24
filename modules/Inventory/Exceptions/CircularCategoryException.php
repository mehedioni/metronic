<?php

namespace Modules\Inventory\Exceptions;

class CircularCategoryException extends InventoryException
{
    public static function make(): self
    {
        return new self('A category cannot be its own parent or a child of one of its descendants.');
    }

    public function errorKey(): string
    {
        return 'parent_id';
    }
}
