<?php

declare(strict_types=1);
use Aliziodev\ProductCatalog\Models\Product;

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
    | Search
    |--------------------------------------------------------------------------
    | The default search driver. Built-in: 'database', 'scout'.
    |
    | database — Pure Eloquent LIKE / FULLTEXT search. Works out of the box
    |            with no extra dependencies. Best for small-to-medium catalogs.
    |
    | scout    — Delegates text search to Laravel Scout (Algolia, Meilisearch,
    |            Typesense, or the Scout database engine). Requires:
    |              1. composer require laravel/scout
    |              2. Configure a Scout driver in config/scout.php
    |              3. Add Laravel\Scout\Searchable + the package's Searchable
    |                 concern to your Product model, then run scout:import.
    |
    | Custom drivers can be registered via:
    |   ProductCatalog::extendSearch('my-driver', fn($app) => new MyDriver());
    */
    'search' => [
        'driver' => env('PRODUCT_CATALOG_SEARCH_DRIVER', 'database'),

        /*
         | model — The Scout-searchable model class used by ScoutSearchDriver.
         |
         | Set this to your application Product model after you extend the package
         | base model and add Laravel\Scout\Searchable plus the package
         | Concerns\Searchable trait.
         */
        'model' => Product::class,

        /*
         | fulltext — Enable MySQL/MariaDB FULLTEXT search for the database driver.
         |
         | When false (default): uses LIKE "%term%" — works on all databases
         |   including SQLite, but cannot use B-tree indexes (slow on large tables).
         |
         | When true: uses MATCH() AGAINST() — requires:
         |   1. MySQL or MariaDB (not supported by SQLite/PostgreSQL)
         |   2. A FULLTEXT index on the products table. Add it via migration:
         |        $table->fullText(['name', 'short_description']);
         |   3. Keep this false in your testing environment (SQLite).
         */
        'fulltext' => env('PRODUCT_CATALOG_SEARCH_FULLTEXT', false),
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
