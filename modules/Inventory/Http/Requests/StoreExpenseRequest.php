<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Inventory\Enums\ExpenseCategory;

class StoreExpenseRequest extends FormRequest
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
            // An expense cannot belong to a day that has not happened yet.
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', Rule::in(ExpenseCategory::values())],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'reference' => ['nullable', 'string', 'max:120'],
            'supplier_id' => ['nullable', 'integer', 'min:1', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
