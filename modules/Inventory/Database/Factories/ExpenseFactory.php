<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Enums\ExpenseCategory;
use Modules\Inventory\Models\Expense;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'spent_on' => now()->toDateString(),
            'category' => fake()->randomElement(ExpenseCategory::values()),
            'amount' => fake()->randomFloat(2, 10, 800),
            'currency' => 'USD',
            'description' => fake()->sentence(4),
        ];
    }

    public function on(string $date): static
    {
        return $this->state(fn (): array => ['spent_on' => $date]);
    }

    public function category(ExpenseCategory $category): static
    {
        return $this->state(fn (): array => ['category' => $category]);
    }
}
