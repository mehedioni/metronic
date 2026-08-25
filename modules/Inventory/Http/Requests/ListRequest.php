<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared validation for every index endpoint in this module. Authorization is
 * handled by the controllers' resource policies, so this class only has to
 * keep filter input sane — an unvalidated "per_page" is a trivial way to ask
 * the database for a million rows.
 */
class ListRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:40'],
            'source' => ['nullable', 'string', 'max:40'],
            'type' => ['nullable', 'string', 'max:40'],
            'category_id' => ['nullable', 'uuid'],
            // Expense category, which is an enum value rather than a record id.
            'category' => ['nullable', 'string', 'max:40'],
            'parent_id' => ['nullable', 'uuid'],
            'supplier_id' => ['nullable', 'uuid'],
            'customer_id' => ['nullable', 'uuid'],
            'product_id' => ['nullable', 'uuid'],
            'product_variant_id' => ['nullable', 'uuid'],
            'order_id' => ['nullable', 'uuid'],
            'user_id' => ['nullable', 'integer'],
            'country' => ['nullable', 'string', 'size:2'],
            'low_stock' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'direction_flow' => ['nullable', 'in:inbound,outbound'],
            'sort' => ['nullable', 'string', 'max:40'],
            'direction' => ['nullable', 'in:asc,desc'],
            'reorder_within' => ['nullable', 'integer', 'between:1,365'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * Validated filters with booleans already cast.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            ...$this->validated(),
            'low_stock' => $this->boolean('low_stock'),
        ];
    }
}
