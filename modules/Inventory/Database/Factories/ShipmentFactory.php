<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Inventory\Enums\ShipmentStatus;
use Modules\Inventory\Models\Order;
use Modules\Inventory\Models\Shipment;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shipment_number' => 'SHP-'.Str::upper(Str::random(8)),
            'order_id' => Order::factory()->confirmed(),
            'status' => ShipmentStatus::Pending,
            'carrier' => fake()->randomElement(['UPS', 'FedEx', 'DHL']),
        ];
    }
}
