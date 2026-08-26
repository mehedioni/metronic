<?php

/*
|--------------------------------------------------------------------------
| Application File Storage
|--------------------------------------------------------------------------
|
| Everything the application stores — product images today, anything else
| later — goes through App\Core\Services\FileStorageService, which reads the
| disk from here. Business logic never names a disk, so switching provider is
| a configuration change:
|
|   FILES_DISK=public   ->   FILES_DISK=s3
|
| Falls back to Laravel's own FILESYSTEM_DISK, then to "public", because an
| image the browser has to load needs a disk that can produce a URL.
|
*/

return [

    'disk' => env('FILES_DISK', env('FILESYSTEM_DISK', 'public')),

    /*
    | Logical roots. Callers ask for a path key rather than typing a folder,
    | so the layout can be reorganised in one place.
    */
    'paths' => [
        'products' => 'products',
        'categories' => 'categories',
        'suppliers' => 'suppliers',
        'customers' => 'customers',
        'expenses' => 'expenses',
        'users' => 'users',
        'settings' => 'settings',
    ],

    /*
    | What an image upload is allowed to be. Shared by every form request that
    | accepts one, so the rule cannot drift between screens.
    */
    'images' => [
        'max_kilobytes' => (int) env('FILES_IMAGE_MAX_KB', 5120),
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'],
        'max_per_product' => (int) env('FILES_MAX_IMAGES_PER_PRODUCT', 12),
    ],

    /*
    | Signed, expiring URLs. Off for public product imagery — a signed URL
    | changes on every render, which defeats browser and CDN caching. Turn it
    | on for a private disk, where the alternative is streaming every byte
    | through PHP.
    */
    'signed_urls' => (bool) env('FILES_SIGNED_URLS', false),
    'signed_url_minutes' => (int) env('FILES_SIGNED_URL_MINUTES', 15),

];
