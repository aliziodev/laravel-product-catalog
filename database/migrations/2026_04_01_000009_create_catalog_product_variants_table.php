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

        Schema::create($prefix.'product_variants', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('product_id')->constrained($prefix.'products')->cascadeOnDelete();
            $table->string('sku')->nullable()->unique();
            $table->decimal('price', 12, 4);
            $table->decimal('compare_price', 12, 4)->nullable();
            $table->decimal('cost_price', 12, 4)->nullable();
            $table->decimal('weight', 10, 3)->nullable();
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->json('meta')->nullable()->after('height');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
            $table->index(['product_id', 'is_default']);
            $table->index(['product_id', 'position']);
            $table->index('sku');
        });
    }

    public function down(): void
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        Schema::dropIfExists($prefix.'product_variants');
    }
};
