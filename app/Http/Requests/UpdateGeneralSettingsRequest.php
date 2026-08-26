<?php

namespace App\Http\Requests;

use App\Core\Support\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store-wide settings. Guarded by the settings.manage permission on the route.
 */
class UpdateGeneralSettingsRequest extends FormRequest
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
            'company_name' => ['required', 'string', 'max:120'],

            // Only a configured currency may be chosen; the list is
            // config/currencies.php, so adding one is configuration.
            'currency' => ['required', 'string', Rule::in(Currency::codes())],

            'logo' => [
                'nullable', 'file', 'image',
                'mimes:'.implode(',', (array) config('files.images.mimes', ['jpg', 'png'])),
                'max:'.(int) config('files.images.max_kilobytes', 5120),
            ],

            // Removing the logo is its own intent, distinct from not sending one.
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }
}
