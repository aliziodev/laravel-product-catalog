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

        Schema::create($prefix.'variant_option_values', function (Blueprint $table) use ($prefix) {
            $table->foreignId('variant_id')->constrained($prefix.'product_variants')->cascadeOnDelete();
            $table->foreignId('option_value_id')->constrained($prefix.'product_option_values')->cascadeOnDelete();
            $table->primary(['variant_id', 'option_value_id']);

            $table->index('option_value_id');
        });
    }

    public function down(): void
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        Schema::dropIfExists($prefix.'variant_option_values');
    }
};
