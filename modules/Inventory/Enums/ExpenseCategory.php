<?php

namespace Modules\Inventory\Enums;

/**
 * What an expense was for.
 *
 * Deliberately a fixed list rather than a free-text field: a daily profit
 * report is only comparable if the same kind of spend lands in the same bucket
 * every time. "Other" is the escape hatch, and the description carries the
 * detail.
 *
 * Note that stock purchases are NOT here. What inventory costs the store
 * reaches the report as cost of goods sold, taken from the order line it was
 * sold on — counting a purchase as an expense as well would subtract the same
 * money twice.
 */
enum ExpenseCategory: string
{
    case Rent = 'rent';
    case Utilities = 'utilities';
    case Salaries = 'salaries';
    case Marketing = 'marketing';
    case Logistics = 'logistics';
    case Equipment = 'equipment';
    case Maintenance = 'maintenance';
    case Fees = 'fees';
    case Taxes = 'taxes';
    case Other = 'other';

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
