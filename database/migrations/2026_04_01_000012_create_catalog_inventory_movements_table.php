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

        Schema::create($prefix.'inventory_movements', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('variant_id')->constrained($prefix.'product_variants')->cascadeOnDelete();
            $table->string('type');
            $table->integer('delta');
            $table->unsignedInteger('quantity_before');
            $table->unsignedInteger('quantity_after');

            // Populated for Reserve and Release movements (null for stock-only changes).
            // For Commit movements both quantity_* and reserved_* columns are filled.
            $table->unsignedInteger('reserved_before')->nullable();
            $table->unsignedInteger('reserved_after')->nullable();

            $table->string('reason')->nullable();
            $table->nullableMorphs('referenceable');  // auto-creates composite index
            $table->timestamp('created_at')->useCurrent();

            $table->index(['variant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        Schema::dropIfExists($prefix.'inventory_movements');
    }
};
