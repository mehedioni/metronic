<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Inventory\Enums\OrderStatus;
use Modules\Inventory\Models\Order;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.Str::upper(Str::random(8)),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->unique()->safeEmail(),
            'status' => OrderStatus::Pending,
            'currency' => 'USD',
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => OrderStatus::Draft]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }
}
