<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    */

    'ssr' => [
        'enabled' => true,
        'url' => 'http://127.0.0.1:13714',
    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | Module pages are addressed "Inventory::Products/Index" and live inside
    | each module's own Resources/js/pages directory, so Inertia's
    | page-existence check cannot resolve them from a page path. Turning it
    | off lets tests use
    | assertInertia() on module pages; the resolver in resources/js/app.ts is
    | what actually has to find the file.
    |
    */

    'testing' => [
        'ensure_pages_exist' => false,

        'page_paths' => [
            resource_path('js/pages'),
        ],

        'page_extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],
    ],

];
