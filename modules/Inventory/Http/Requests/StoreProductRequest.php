<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Inventory\Enums\ProductStatus;
use Modules\Inventory\Enums\ProductType;
use Modules\Inventory\Enums\RecordStatus;

class StoreProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->whereNull('deleted_at')],
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('products', 'sku')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'uuid', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'primary_supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'type' => ['nullable', Rule::in(ProductType::values())],
            'status' => ['nullable', Rule::in(ProductStatus::values())],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'selling_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'meta' => ['nullable', 'array'],

            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'uuid'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:80', 'distinct'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:255'],
            'variants.*.options' => ['nullable', 'array'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.selling_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'variants.*.status' => ['nullable', Rule::in(RecordStatus::values())],

            'suppliers' => ['nullable', 'array'],
            'suppliers.*.supplier_id' => [
                'required_with:suppliers',
                'uuid',
                Rule::exists('suppliers', 'id')->whereNull('deleted_at'),
            ],
            'suppliers.*.product_variant_id' => ['nullable', 'uuid'],
            'suppliers.*.supplier_sku' => ['nullable', 'string', 'max:80'],
            'suppliers.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'suppliers.*.minimum_order_quantity' => ['nullable', 'integer', 'min:0'],
            'suppliers.*.lead_time_days' => ['nullable', 'integer', 'min:0'],
            'suppliers.*.is_preferred' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->filled('slug')
                ? Str::slug($this->string('slug')->toString())
                : Str::slug($this->string('name')->toString()),
        ]);
    }

    /**
     * A variable product without variants has nothing to stock, and a variant
     * SKU must be globally unique across products.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $type = $this->input('type', ProductType::Simple->value);
                $variants = $this->input('variants', []);

                if ($type === ProductType::Variable->value && $variants === []) {
                    $validator->errors()->add('variants', 'A variable product needs at least one variant.');
                }
            },
        ];
    }
}
