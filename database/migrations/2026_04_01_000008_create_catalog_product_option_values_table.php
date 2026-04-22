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

        Schema::create($prefix.'product_option_values', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('option_id')->constrained($prefix.'product_options')->cascadeOnDelete();
            $table->string('value');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['option_id', 'position']);
        });
    }

    public function down(): void
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        Schema::dropIfExists($prefix.'product_option_values');
    }
};
