<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        Schema::create($prefix.'inventory_items', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('variant_id')->unique()->constrained($prefix.'product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedSmallInteger('low_stock_threshold')->nullable();
            $table->string('policy')->default('track');
            $table->timestamps();
            // Speeds up inStock() and lowStock() scopes that filter by policy value.
            // Default name ({prefix}inventory_items_policy_index). Releases before 1.7.1
            // used the fixed name idx_catalog_inventory_items_policy, which ignored the
            // prefix; 2026_10_31_000001_prefix_catalog_index_names renames it on those installs.
            $table->index('policy');
        });
    }

    public function down(): void
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        Schema::dropIfExists($prefix.'inventory_items');
    }
};
