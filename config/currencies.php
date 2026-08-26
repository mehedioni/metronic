<?php

/*
|--------------------------------------------------------------------------
| Currencies
|--------------------------------------------------------------------------
|
| The currencies the store can trade in. Which one is active is a setting,
| not configuration — an operator changes it in Settings → General, and it
| becomes the default on every new order and expense. Existing records keep
| the currency they were written with, so history never silently changes
| meaning.
|
| Amounts are formatted from `symbol` and `position` rather than by locale,
| so a figure reads the same wherever it is rendered.
|
*/

return [

    'default' => env('CURRENCY', 'USD'),

    'available' => [

        'USD' => [
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'position' => 'before',
            'decimals' => 2,
        ],

        'EUR' => [
            'code' => 'EUR',
            'name' => 'Euro',
            'symbol' => '€',
            'position' => 'before',
            'decimals' => 2,
        ],

        'GBP' => [
            'code' => 'GBP',
            'name' => 'Pound Sterling',
            'symbol' => '£',
            'position' => 'before',
            'decimals' => 2,
        ],

        'BDT' => [
            'code' => 'BDT',
            'name' => 'Taka',
            'symbol' => '৳',
            'position' => 'before',
            'decimals' => 2,
        ],

    ],

];
