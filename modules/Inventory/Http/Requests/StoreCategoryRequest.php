<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Inventory\Enums\RecordStatus;

class StoreCategoryRequest extends FormRequest
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
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('categories', 'slug')->whereNull('deleted_at')],
            'parent_id' => ['nullable', 'integer', 'min:1', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(RecordStatus::values())],
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
}
