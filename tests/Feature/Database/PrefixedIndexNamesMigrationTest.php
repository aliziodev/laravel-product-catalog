<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

function prefixIndexNamesMigration(): Migration
{
    return require __DIR__.'/../../../database/migrations/2026_10_31_000001_prefix_catalog_index_names.php';
}

/**
 * @return list<string>
 */
function catalogIndexNames(string $table): array
{
    return collect(Schema::getIndexes($table))->pluck('name')->sort()->values()->all();
}

/**
 * Give the two indexes the fixed names that releases before 1.7.1 created.
 */
function useIndexNamesFromOldReleases(): void
{
    Schema::table('catalog_products', fn (Blueprint $table) => $table->renameIndex('catalog_products_name_index', 'idx_catalog_products_name'));
    Schema::table('catalog_inventory_items', fn (Blueprint $table) => $table->renameIndex('catalog_inventory_items_policy_index', 'idx_catalog_inventory_items_policy'));
}

it('creates both indexes with prefixed names on a fresh install', function () {
    expect(Schema::hasIndex('catalog_products', 'catalog_products_name_index'))->toBeTrue()
        ->and(Schema::hasIndex('catalog_inventory_items', 'catalog_inventory_items_policy_index'))->toBeTrue()
        ->and(Schema::hasIndex('catalog_products', 'idx_catalog_products_name'))->toBeFalse()
        ->and(Schema::hasIndex('catalog_inventory_items', 'idx_catalog_inventory_items_policy'))->toBeFalse();
});

it('renames the fixed index names that older releases created', function () {
    $expected = [
        'catalog_products' => catalogIndexNames('catalog_products'),
        'catalog_inventory_items' => catalogIndexNames('catalog_inventory_items'),
    ];

    useIndexNamesFromOldReleases();
    expect(Schema::hasIndex('catalog_products', 'idx_catalog_products_name'))->toBeTrue();

    prefixIndexNamesMigration()->up();

    expect(catalogIndexNames('catalog_products'))->toBe($expected['catalog_products'])
        ->and(catalogIndexNames('catalog_inventory_items'))->toBe($expected['catalog_inventory_items'])
        ->and(Schema::hasIndex('catalog_products', ['name']))->toBeTrue()
        ->and(Schema::hasIndex('catalog_inventory_items', ['policy']))->toBeTrue();
});

it('changes nothing on a fresh install and can run more than once', function () {
    $before = [catalogIndexNames('catalog_products'), catalogIndexNames('catalog_inventory_items')];

    $migration = prefixIndexNamesMigration();
    $migration->up();
    $migration->up();

    expect([catalogIndexNames('catalog_products'), catalogIndexNames('catalog_inventory_items')])->toBe($before);
});

it('migrates a second table prefix into the same schema', function () {
    // The catalog_ tables are already migrated; PostgreSQL and SQLite used to
    // reject the second set because idx_catalog_* index names already existed.
    config(['product-catalog.table_prefix' => 'shop_']);

    $migrations = array_map(
        fn (string $file): Migration => require $file,
        glob(__DIR__.'/../../../database/migrations/*.php'),
    );

    try {
        foreach ($migrations as $migration) {
            $migration->up();
        }

        expect(Schema::hasTable('shop_products'))->toBeTrue()
            ->and(Schema::hasIndex('shop_products', 'shop_products_name_index'))->toBeTrue()
            ->and(Schema::hasIndex('shop_inventory_items', 'shop_inventory_items_policy_index'))->toBeTrue()
            ->and(Schema::hasIndex('catalog_products', 'catalog_products_name_index'))->toBeTrue();
    } finally {
        // These tables are not in the migrations table, so the test rollback
        // would leave them behind on a persistent database.
        foreach (array_reverse($migrations) as $migration) {
            $migration->down();
        }

        // The test rollback reads the prefix too; it must drop the catalog_ tables.
        config(['product-catalog.table_prefix' => 'catalog_']);
    }
});
