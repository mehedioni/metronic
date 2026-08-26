<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The signed-in user's own profile. No permission: everyone may edit their own.
 *
 * The email address is deliberately not accepted — it identifies the account
 * and is changed by an administrator through the Access module, so a stray
 * field here cannot let someone move their own login.
 */
class UpdateProfileRequest extends FormRequest
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
        ];
    }
}
