<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Optional per-line quantities for a partial fulfilment. Sending nothing
 * fulfils every outstanding line in full.
 */
class FulfillOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'lines' => ['nullable', 'array'],
            'lines.*' => ['integer', 'min:0'],
        ];
    }

    /**
     * The action takes order_item_id => quantity; anything at zero is dropped
     * so an untouched line is never treated as an explicit request.
     *
     * @return array<string, int>
     */
    public function lines(): array
    {
        return collect((array) $this->validated('lines', []))
            ->map(fn (mixed $quantity): int => (int) $quantity)
            ->filter(fn (int $quantity): bool => $quantity > 0)
            ->all();
    }
}
