<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderProductImagesRequest extends FormRequest
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
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function imageIds(): array
    {
        return array_map('intval', (array) $this->validated('images', []));
    }
}
