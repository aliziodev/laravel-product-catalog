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

        Schema::create($prefix.'product_tags', function (Blueprint $table) use ($prefix) {
            $table->foreignId('product_id')->constrained($prefix.'products')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained($prefix.'tags')->cascadeOnDelete();
            $table->primary(['product_id', 'tag_id']);

            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        Schema::dropIfExists($prefix.'product_tags');
    }
};
