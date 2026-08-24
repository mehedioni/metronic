<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Initial Super Admin
    |--------------------------------------------------------------------------
    |
    | Seeding creates (or promotes) this account and gives it the Super Admin
    | role, which bypasses every permission check via Gate::before. Set these
    | in the environment before the first deploy and rotate the password
    | immediately after the first sign-in.
    |
    | An existing account can be promoted at any time with:
    |   php artisan access:super-admin user@example.com
    |
    */
    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
        'email' => env('SUPER_ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('SUPER_ADMIN_PASSWORD', 'password'),
    ],

];
