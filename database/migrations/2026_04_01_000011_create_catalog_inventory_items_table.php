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
        });
    }

    public function down(): void
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        Schema::dropIfExists($prefix.'inventory_items');
    }
};
