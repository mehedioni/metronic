<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Inventory\Models\Order;

class StoreShipmentRequest extends FormRequest
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
            'carrier' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'uuid'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Lines must belong to the order in the route and may not exceed what is
     * still unshipped — otherwise a shipment could deduct stock twice.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $order = $this->route('order');

                if (! $order instanceof Order) {
                    return;
                }

                $order->loadMissing('items');

                foreach ((array) $this->input('items', []) as $index => $line) {
                    $item = $order->items->firstWhere('id', $line['order_item_id'] ?? null);

                    if (! $item) {
                        $validator->errors()->add("items.{$index}.order_item_id", 'This line does not belong to the order.');

                        continue;
                    }

                    if ((int) ($line['quantity'] ?? 0) > $item->outstandingQuantity()) {
                        $validator->errors()->add(
                            "items.{$index}.quantity",
                            "Only {$item->outstandingQuantity()} unit(s) remain unshipped on this line.",
                        );
                    }
                }
            },
        ];
    }
}
