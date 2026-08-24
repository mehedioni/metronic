<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Inventory\Enums\InboundReceiptStatus;
use Modules\Inventory\Enums\InboundSource;
use Modules\Inventory\Enums\RecordStatus;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Supplier;

class StoreInboundReceiptRequest extends FormRequest
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
            'reference_number' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('inbound_receipts', 'reference_number')->whereNull('deleted_at'),
            ],
            'supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'source' => ['required', Rule::in(InboundSource::values())],
            'status' => ['nullable', Rule::in([InboundReceiptStatus::Draft->value, InboundReceiptStatus::Pending->value])],
            'received_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.product_variant_id' => ['nullable', 'uuid', Rule::exists('product_variants', 'id')->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.supplier_sku' => ['nullable', 'string', 'max:80'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Cross-field rules the rule list cannot express: supplier-sourced
     * receipts need an active supplier, and a variant must belong to the
     * product it is listed under.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateSupplier($validator),
            fn (Validator $validator) => $this->validateVariantOwnership($validator),
        ];
    }

    private function validateSupplier(Validator $validator): void
    {
        $source = InboundSource::tryFrom((string) $this->input('source'));

        if (! $source?->requiresSupplier()) {
            return;
        }

        $supplier = $this->input('supplier_id')
            ? Supplier::query()->find($this->input('supplier_id'))
            : null;

        if (! $supplier) {
            $validator->errors()->add('supplier_id', 'A supplier is required for this source.');

            return;
        }

        if ($supplier->status !== RecordStatus::Active) {
            $validator->errors()->add('supplier_id', 'Stock cannot be received from an inactive supplier.');
        }
    }

    private function validateVariantOwnership(Validator $validator): void
    {
        foreach ((array) $this->input('items', []) as $index => $item) {
            if (empty($item['product_variant_id'])) {
                continue;
            }

            $belongs = Product::query()
                ->whereKey($item['product_id'] ?? null)
                ->whereHas('variants', fn ($query) => $query->whereKey($item['product_variant_id']))
                ->exists();

            if (! $belongs) {
                $validator->errors()->add(
                    "items.{$index}.product_variant_id",
                    'The selected variant does not belong to the selected product.',
                );
            }
        }
    }
}
