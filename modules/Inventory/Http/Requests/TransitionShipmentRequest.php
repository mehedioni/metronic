<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Inventory\Enums\ShipmentStatus;

class TransitionShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * "shipped" is excluded on purpose: dispatching moves stock and has its
     * own endpoint.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in(array_diff(ShipmentStatus::values(), [ShipmentStatus::Shipped->value])),
            ],
        ];
    }
}
