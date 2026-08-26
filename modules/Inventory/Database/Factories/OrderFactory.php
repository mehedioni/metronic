<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Support\OrderStatuses;

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
            'status_id' => OrderStatuses::key('pending')->id,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status_id' => OrderStatuses::key('draft')->id]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status_id' => OrderStatuses::key('confirmed')->id,
            'confirmed_at' => now(),
        ]);
    }
}
