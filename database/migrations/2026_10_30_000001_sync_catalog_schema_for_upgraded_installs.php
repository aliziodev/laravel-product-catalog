<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brings installs created by older releases up to the current schema.
 *
 * Some releases changed create migrations that had already shipped, so installs
 * that ran those migrations earlier never received the change:
 *  - v1.4.1: brands.logo_url renamed to logo_path
 *  - v1.5.0: indexes on products.name and inventory_items.policy
 *  - v1.6.0: inventory_movements.reserved_before and reserved_after
 *
 * Every step checks the current schema first, so on a fresh install this
 * migration changes nothing. The two indexes are looked up by column, because
 * their name changed in 1.7.1 (see 2026_10_31_000001_prefix_catalog_index_names).
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        $brands = $prefix.'brands';

        if (Schema::hasColumn($brands, 'logo_url') && ! Schema::hasColumn($brands, 'logo_path')) {
            Schema::table($brands, function (Blueprint $table) {
                $table->renameColumn('logo_url', 'logo_path');
            });
        }

        $products = $prefix.'products';

        if (! Schema::hasIndex($products, ['name'])) {
            Schema::table($products, function (Blueprint $table) {
                $table->index('name');
            });
        }

        $inventoryItems = $prefix.'inventory_items';

        if (! Schema::hasIndex($inventoryItems, ['policy'])) {
            Schema::table($inventoryItems, function (Blueprint $table) {
                $table->index('policy');
            });
        }

        $movements = $prefix.'inventory_movements';

        if (! Schema::hasColumn($movements, 'reserved_before')) {
            Schema::table($movements, function (Blueprint $table) {
                $table->unsignedInteger('reserved_before')->nullable()->after('quantity_after');
            });
        }

        if (! Schema::hasColumn($movements, 'reserved_after')) {
            Schema::table($movements, function (Blueprint $table) {
                $table->unsignedInteger('reserved_after')->nullable()->after('reserved_before');
            });
        }
    }

    /**
     * Intentionally empty. On a fresh install these columns and indexes belong to
     * the create migrations, so removing them here would leave a broken schema.
     */
    public function down(): void {}
};
