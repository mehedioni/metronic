<?php

namespace Modules\Inventory\Enums;

/**
 * Shared active/inactive lifecycle used by categories, suppliers and variants.
 */
enum RecordStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
