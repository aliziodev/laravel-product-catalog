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

        Schema::create($prefix.'product_options', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('product_id')->constrained($prefix.'products')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'position']);
        });
    }

    public function down(): void
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        Schema::dropIfExists($prefix.'product_options');
    }
};
