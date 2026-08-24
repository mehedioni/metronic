<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Inventory\Enums\ProductStatus;
use Modules\Inventory\Enums\ProductType;
use Modules\Inventory\Models\Product;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'description' => fake()->paragraph(),
            'type' => ProductType::Simple,
            'status' => ProductStatus::Active,
            'cost_price' => fake()->randomFloat(2, 5, 200),
            'selling_price' => fake()->randomFloat(2, 10, 400),
            'low_stock_threshold' => 5,
        ];
    }

    public function variable(): static
    {
        return $this->state(fn (): array => ['type' => ProductType::Variable]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => ProductStatus::Archived]);
    }
}
