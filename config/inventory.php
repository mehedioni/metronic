<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Negative Stock
    |--------------------------------------------------------------------------
    |
    | When false (the default) any operation that would push a stockable unit
    | below zero on-hand is rejected with an InsufficientStockException.
    | Enable only if the business genuinely back-orders against negative stock.
    |
    */
    'allow_negative_stock' => env('INVENTORY_ALLOW_NEGATIVE_STOCK', false),

    /*
    |--------------------------------------------------------------------------
    | Overselling
    |--------------------------------------------------------------------------
    |
    | When false, confirming an order that exceeds available (on-hand minus
    | already reserved) stock is rejected.
    |
    */
    'allow_overselling' => env('INVENTORY_ALLOW_OVERSELLING', false),

    /*
    |--------------------------------------------------------------------------
    | Document Number Prefixes
    |--------------------------------------------------------------------------
    |
    | Used when a reference number is not supplied by the caller.
    |
    */
    'number_prefixes' => [
        'inbound_receipt' => 'GRN',
        'order' => 'ORD',
        'customer' => 'CUS',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */
    'dashboard' => [
        'recent_limit' => 10,
        'low_stock_limit' => 10,

        // Days covered by the dashboard's trend charts, and therefore also the
        // window the period-over-period comparison uses.
        'trend_days' => 14,
    ],

];
