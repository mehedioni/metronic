<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Inventory\Enums\StockMovementType;
use Modules\Inventory\Models\Product;

class AdjustStockRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'min:1', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'product_variant_id' => ['nullable', 'integer', 'min:1', Rule::exists('product_variants', 'id')->whereNull('deleted_at')],
            'type' => ['required', Rule::in(StockMovementType::manualValues())],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * A stock decrease without a stated reason is unauditable, so the reason
     * is mandatory in that direction only.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $type = StockMovementType::tryFrom((string) $this->input('type'));

                if ($type && ! $type->isInbound() && ! $this->filled('reason')) {
                    $validator->errors()->add('reason', 'A reason is required when stock is removed.');
                }

                if ($this->filled('product_variant_id')) {
                    $belongs = Product::query()
                        ->whereKey($this->input('product_id'))
                        ->whereHas('variants', fn ($query) => $query->whereKey($this->input('product_variant_id')))
                        ->exists();

                    if (! $belongs) {
                        $validator->errors()->add(
                            'product_variant_id',
                            'The selected variant does not belong to the selected product.',
                        );
                    }
                }
            },
        ];
    }
}
