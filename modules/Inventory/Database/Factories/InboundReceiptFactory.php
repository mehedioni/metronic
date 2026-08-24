<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Inventory\Enums\InboundReceiptStatus;
use Modules\Inventory\Enums\InboundSource;
use Modules\Inventory\Models\InboundReceipt;
use Modules\Inventory\Models\Supplier;

/**
 * @extends Factory<InboundReceipt>
 */
class InboundReceiptFactory extends Factory
{
    protected $model = InboundReceipt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_number' => 'GRN-'.Str::upper(Str::random(8)),
            'supplier_id' => Supplier::factory(),
            'source' => InboundSource::Supplier,
            'status' => InboundReceiptStatus::Draft,
            'received_date' => now()->toDateString(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => InboundReceiptStatus::Pending]);
    }
}
