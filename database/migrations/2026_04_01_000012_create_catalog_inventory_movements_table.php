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
            $table->string('reason')->nullable();
            $table->nullableMorphs('referenceable');
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
