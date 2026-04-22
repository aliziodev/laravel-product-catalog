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

        Schema::create($prefix.'categories', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained($prefix.'categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id');
            $table->index('name');
            $table->index('position');
        });
    }

    public function down(): void
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        Schema::dropIfExists($prefix.'categories');
    }
};
