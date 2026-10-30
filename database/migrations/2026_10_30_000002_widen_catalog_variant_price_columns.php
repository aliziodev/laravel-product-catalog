<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widens the variant price columns from decimal(12,4) to decimal(19,4).
 *
 * decimal(12,4) stops at 99,999,999.9999, which is too low for currencies
 * such as IDR. Only columns that still have the shipped decimal(12,4)
 * definition are changed, so a column you already altered is left alone.
 *
 * SQLite does not enforce decimal precision, so there is nothing to do there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        $table = config('product-catalog.table_prefix', 'catalog_').'product_variants';

        $columnsToWiden = collect(Schema::getColumns($table))
            ->filter(fn (array $column) => in_array($column['name'], ['price', 'compare_price', 'cost_price'], true))
            ->filter(fn (array $column) => preg_match('/\(\s*12\s*,\s*4\s*\)/', $column['type']) === 1)
            ->pluck('nullable', 'name');

        if ($columnsToWiden->isEmpty()) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columnsToWiden) {
            foreach ($columnsToWiden as $name => $nullable) {
                $blueprint->decimal($name, 19, 4)->nullable($nullable)->change();
            }
        });
    }

    /**
     * Intentionally empty. Narrowing the columns again would fail, or lose
     * data, as soon as one price is above 99,999,999.9999.
     */
    public function down(): void {}
};
