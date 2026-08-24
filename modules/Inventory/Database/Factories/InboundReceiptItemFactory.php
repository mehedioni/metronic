<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\InboundReceipt;
use Modules\Inventory\Models\InboundReceiptItem;
use Modules\Inventory\Models\Product;

/**
 * @extends Factory<InboundReceiptItem>
 */
class InboundReceiptItemFactory extends Factory
{
    protected $model = InboundReceiptItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inbound_receipt_id' => InboundReceipt::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 50),
            'unit_cost' => fake()->randomFloat(2, 1, 100),
        ];
    }
}
