<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters for the daily trading report.
 */
class ReportRequest extends FormRequest
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
            'from' => ['nullable', 'date'],
            // No after_or_equal rule: ReportService swaps a backwards range
            // rather than rejecting it, because that is a typo and not a
            // request for an empty report.
            'to' => ['nullable', 'date'],
            'customer' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->validated();
    }
}
