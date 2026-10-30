<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Aliziodev\ProductCatalog\Facades\ProductCatalog;
use Aliziodev\ProductCatalog\Models\InventoryItem;
use Aliziodev\ProductCatalog\Models\InventoryMovement;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

function syncSchemaMigration(): Migration
{
    return require __DIR__.'/../../../database/migrations/2026_10_30_000001_sync_catalog_schema_for_upgraded_installs.php';
}

/**
 * Put the schema back to what an install from v1.4.0 or earlier still has:
 * the old brand logo column, no name/policy indexes, no reserved_* columns.
 */
function downgradeToOldReleaseSchema(): void
{
    Schema::table('catalog_brands', fn (Blueprint $table) => $table->renameColumn('logo_path', 'logo_url'));
    Schema::table('catalog_products', fn (Blueprint $table) => $table->dropIndex('idx_catalog_products_name'));
    Schema::table('catalog_inventory_items', fn (Blueprint $table) => $table->dropIndex('idx_catalog_inventory_items_policy'));
    Schema::table('catalog_inventory_movements', fn (Blueprint $table) => $table->dropColumn(['reserved_before', 'reserved_after']));
}

/**
 * @return array<string, list<string>>
 */
function catalogSchemaSnapshot(): array
{
    $snapshot = [];

    foreach (['catalog_brands', 'catalog_products', 'catalog_inventory_items', 'catalog_inventory_movements'] as $table) {
        $snapshot[$table.'.columns'] = Schema::getColumnListing($table);
        $snapshot[$table.'.indexes'] = collect(Schema::getIndexes($table))->pluck('name')->sort()->values()->all();
    }

    return $snapshot;
}

it('leaves a fresh install unchanged', function () {
    $before = catalogSchemaSnapshot();

    syncSchemaMigration()->up();

    expect(catalogSchemaSnapshot())->toBe($before);
});

it('restores the columns and indexes that installs from older releases never received', function () {
    $current = catalogSchemaSnapshot();
    downgradeToOldReleaseSchema();

    expect(Schema::hasColumn('catalog_inventory_movements', 'reserved_before'))->toBeFalse()
        ->and(Schema::hasColumn('catalog_brands', 'logo_url'))->toBeTrue();

    syncSchemaMigration()->up();

    expect(Schema::hasColumn('catalog_brands', 'logo_path'))->toBeTrue()
        ->and(Schema::hasColumn('catalog_brands', 'logo_url'))->toBeFalse()
        ->and(Schema::hasIndex('catalog_products', 'idx_catalog_products_name'))->toBeTrue()
        ->and(Schema::hasIndex('catalog_inventory_items', 'idx_catalog_inventory_items_policy'))->toBeTrue()
        ->and(Schema::hasColumn('catalog_inventory_movements', 'reserved_before'))->toBeTrue()
        ->and(Schema::hasColumn('catalog_inventory_movements', 'reserved_after'))->toBeTrue()
        ->and(collect(catalogSchemaSnapshot())->map(fn (array $names) => collect($names)->sort()->values()->all())->all())
        ->toBe(collect($current)->map(fn (array $names) => collect($names)->sort()->values()->all())->all());
});

it('can run more than once', function () {
    downgradeToOldReleaseSchema();

    $migration = syncSchemaMigration();
    $migration->up();
    $migration->up();

    expect(Schema::hasColumn('catalog_inventory_movements', 'reserved_after'))->toBeTrue();
});

it('lets an upgraded install record reservation movements again', function () {
    downgradeToOldReleaseSchema();
    syncSchemaMigration()->up();

    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->reserve($variant, 3);

    $movement = InventoryMovement::where('variant_id', $variant->id)->sole();
    expect($movement->reserved_before)->toBe(0)
        ->and($movement->reserved_after)->toBe(3);
});
