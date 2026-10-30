<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renames the two indexes that releases before 1.7.1 created with a fixed name:
 *  - idx_catalog_products_name          → {prefix}products_name_index
 *  - idx_catalog_inventory_items_policy → {prefix}inventory_items_policy_index
 *
 * The fixed names ignored table_prefix. MySQL scopes index names to a table,
 * but PostgreSQL and SQLite scope them to the whole schema, so installing a
 * second prefix into the same schema failed. The new names are the ones
 * Laravel generates, which is what fresh installs now get.
 *
 * Each rename only runs when the table still has the old name, so this
 * migration changes nothing on a fresh install and can run more than once.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        $this->renameIndex($prefix.'products', 'idx_catalog_products_name', 'name');
        $this->renameIndex($prefix.'inventory_items', 'idx_catalog_inventory_items_policy', 'policy');
    }

    /**
     * Intentionally empty. The old names are what caused the collision, and no
     * package code refers to an index by name.
     */
    public function down(): void {}

    private function renameIndex(string $table, string $oldName, string $column): void
    {
        $newName = $this->defaultIndexName($table, $column);

        if (! Schema::hasIndex($table, $oldName) || Schema::hasIndex($table, $newName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($oldName, $newName) {
            $blueprint->renameIndex($oldName, $newName);
        });
    }

    /**
     * The name Laravel generates for a single-column index, matching
     * Blueprint::createIndexName() including the prefix_indexes option.
     */
    private function defaultIndexName(string $table, string $column): string
    {
        $connection = Schema::getConnection();

        if ($connection->getConfig('prefix_indexes')) {
            $table = $connection->getTablePrefix().$table;
        }

        return str_replace(['-', '.'], '_', strtolower("{$table}_{$column}_index"));
    }
};
