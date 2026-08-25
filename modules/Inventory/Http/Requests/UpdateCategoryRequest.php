<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Inventory\Enums\RecordStatus;

class UpdateCategoryRequest extends FormRequest
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
        $categoryId = $this->route('category')?->getKey();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($categoryId)->whereNull('deleted_at'),
            ],
            'parent_id' => [
                'nullable',
                'integer', 'min:1',
                'different:'.$categoryId,
                Rule::exists('categories', 'id')->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(RecordStatus::values())],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('slug')) {
            $this->merge(['slug' => Str::slug($this->string('slug')->toString())]);
        }
    }
}
