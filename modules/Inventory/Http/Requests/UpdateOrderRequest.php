<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Inventory\Enums\OrderStatus;
use Modules\Inventory\Enums\ProductStatus;
use Modules\Inventory\Models\Product;

class UpdateOrderRequest extends FormRequest
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
            'customer_id' => ['nullable', 'integer', 'min:1', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'customer_name' => ['sometimes', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'delivery_address' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in([OrderStatus::Draft->value, OrderStatus::Pending->value])],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'min:1', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.product_variant_id' => ['nullable', 'integer', 'min:1', Rule::exists('product_variants', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Only sellable products may be ordered, and a variant has to belong to
     * the product on its line.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('items', []) as $index => $item) {
                    $product = Product::query()->find($item['product_id'] ?? null);

                    if ($product && $product->status !== ProductStatus::Active) {
                        $validator->errors()->add("items.{$index}.product_id", 'This product is not available for sale.');
                    }

                    if ($product && ! empty($item['product_variant_id'])
                        && ! $product->variants()->whereKey($item['product_variant_id'])->exists()) {
                        $validator->errors()->add(
                            "items.{$index}.product_variant_id",
                            'The selected variant does not belong to the selected product.',
                        );
                    }
                }
            },
        ];
    }
}
