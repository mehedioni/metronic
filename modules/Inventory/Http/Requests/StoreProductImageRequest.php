<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Image uploads for a product. The limits come from config/files.php so every
 * screen that accepts an image enforces the same rule.
 */
class StoreProductImageRequest extends FormRequest
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
        $images = (array) config('files.images');

        return [
            'images' => ['required', 'array', 'min:1', 'max:'.($images['max_per_product'] ?? 12)],
            'images.*' => [
                'file',
                'image',
                'mimes:'.implode(',', $images['mimes'] ?? ['jpg', 'png']),
                'max:'.($images['max_kilobytes'] ?? 5120),
            ],
            'product_variant_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function images(): array
    {
        return array_values((array) $this->file('images', []));
    }
}
