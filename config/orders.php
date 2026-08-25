<?php

/*
|--------------------------------------------------------------------------
| Order Statuses
|--------------------------------------------------------------------------
|
| The order lifecycle lives here rather than in an enum, so a store can add
| or relabel a status without a code change. Orders store the "id" — never
| the key or the label — so renaming "Quote" tomorrow does not rewrite a
| single row.
|
| Each status declares what it *means*, because the inventory effects are
| bound to those meanings, not to names:
|
|   editable           the order's lines and totals may still change
|   holds_reservation  stock is reserved while the order sits here
|   fulfillable        the order may be handed over from here, which deducts
|                      on-hand stock and releases the reservation
|   cancellable        the order may still be cancelled
|   void               the order was called off; it never counts as trade,
|                      so revenue and customer spend exclude it
|   transitions        statuses this one may move to, by key
|
| Ids are permanent. Reusing one for a different meaning would silently
| restate every historical order, so add a new id instead.
|
*/

return [

    'statuses' => [

        [
            'id' => 1,
            'key' => 'draft',
            // A draft order is a quote: written down, nothing promised.
            'label' => 'Quote',
            'variant' => 'neutral',
            'editable' => true,
            'holds_reservation' => false,
            'fulfillable' => false,
            'cancellable' => true,
            'transitions' => ['pending', 'cancelled'],
        ],

        [
            'id' => 2,
            'key' => 'pending',
            'label' => 'Pending',
            'variant' => 'warning',
            'editable' => true,
            'holds_reservation' => false,
            'fulfillable' => false,
            'cancellable' => true,
            'transitions' => ['confirmed', 'cancelled'],
        ],

        [
            'id' => 3,
            'key' => 'confirmed',
            'label' => 'Confirmed',
            'variant' => 'info',
            'editable' => false,
            'holds_reservation' => true,
            'fulfillable' => true,
            'cancellable' => true,
            'transitions' => ['processing', 'completed', 'cancelled'],
        ],

        [
            'id' => 4,
            'key' => 'processing',
            'label' => 'Processing',
            'variant' => 'info',
            'editable' => false,
            'holds_reservation' => true,
            'fulfillable' => true,
            'cancellable' => true,
            'transitions' => ['completed', 'cancelled'],
        ],

        [
            'id' => 5,
            'key' => 'completed',
            'label' => 'Completed',
            'variant' => 'success',
            'editable' => false,
            'holds_reservation' => false,
            'fulfillable' => false,
            'cancellable' => false,
            'transitions' => [],
        ],

        [
            'id' => 6,
            'key' => 'cancelled',
            'label' => 'Cancelled',
            'variant' => 'danger',
            'editable' => false,
            'holds_reservation' => false,
            'fulfillable' => false,
            'cancellable' => false,
            'void' => true,
            'transitions' => [],
        ],

    ],

    /*
    | Status a new order starts in when the caller names none.
    */
    'default' => 'draft',

    /*
    | Statuses a caller may set directly through a form. Everything else is
    | reached only through an action that carries the inventory effect with it
    | — confirming reserves stock, fulfilling deducts it.
    */
    'assignable' => ['draft', 'pending'],

    /*
    | The status the Quotes screen lists and creates.
    */
    'quote' => 'draft',

];
