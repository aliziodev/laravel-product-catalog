<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Table Prefix
    |--------------------------------------------------------------------------
    | Prefix for all package database tables. Change before running migrations.
    */
    'table_prefix' => env('PRODUCT_CATALOG_TABLE_PREFIX', 'catalog_'),

    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    | The default inventory driver. Built-in: 'database', 'null'.
    | Register custom drivers via ProductCatalog::extend($name, $resolver).
    */
    'inventory' => [
        'driver' => env('PRODUCT_CATALOG_INVENTORY_DRIVER', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Slug
    |--------------------------------------------------------------------------
    | auto_generate : regenerate the slug prefix when the product name changes.
    | separator     : character used between words and before the route key.
    | route_key_length : length of the permanent random suffix appended to slug.
    |                    Clamped to 4–32 automatically. Recommended: 8.
    |                    Values below 4 are silently raised to 4.
    |                    Values above 32 are silently lowered to 32.
    */
    'slug' => [
        'auto_generate' => true,
        'separator' => '-',
        'route_key_length' => (int) env('PRODUCT_CATALOG_ROUTE_KEY_LENGTH', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    | Set enabled to true to register the read-only catalog API routes.
    | Customize prefix and middleware to fit your application.
    */
    'routes' => [
        'enabled' => env('PRODUCT_CATALOG_ROUTES_ENABLED', false),
        'prefix' => env('PRODUCT_CATALOG_ROUTES_PREFIX', 'catalog'),
        'middleware' => ['api'],
    ],

];
