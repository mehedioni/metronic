<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Inventory\Enums\RecordStatus;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\ProductVariant;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $size = fake()->randomElement(['S', 'M', 'L', 'XL']);
        $color = fake()->safeColorName();

        return [
            'product_id' => Product::factory()->variable(),
            'sku' => 'VAR-'.Str::upper(Str::random(8)),
            'name' => "{$size} / {$color}",
            'options' => ['size' => $size, 'color' => $color],
            'cost_price' => fake()->randomFloat(2, 5, 200),
            'selling_price' => fake()->randomFloat(2, 10, 400),
            'low_stock_threshold' => 3,
            'status' => RecordStatus::Active,
        ];
    }
}
