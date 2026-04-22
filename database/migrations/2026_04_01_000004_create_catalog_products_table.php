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

        Schema::create($prefix.'products', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('brand_id')
                ->nullable()
                ->constrained($prefix.'brands')
                ->nullOnDelete();
            $table->foreignId('primary_category_id')
                ->nullable()
                ->constrained($prefix.'categories')
                ->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->string('slug')->unique();
            $table->string('route_key', 32)->nullable()->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('type')->default('simple');
            $table->string('status')->default('draft');
            $table->string('featured_image_path')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('brand_id');
            $table->index('primary_category_id');
            $table->index('type');
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        Schema::dropIfExists($prefix.'products');
    }
};
