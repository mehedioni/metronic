<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Inventory\Enums\ExpenseCategory;

class UpdateExpenseRequest extends FormRequest
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
            'spent_on' => ['sometimes', 'date', 'before_or_equal:today'],
            'category' => ['sometimes', Rule::in(ExpenseCategory::values())],
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:99999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'reference' => ['nullable', 'string', 'max:120'],
            'supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
