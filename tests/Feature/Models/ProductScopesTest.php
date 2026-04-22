<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Enums\ProductStatus;
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;

// --- defaultVariant() ---

it('defaultVariant returns the variant marked as default', function () {
    $product = Product::factory()->create();
    $default = ProductVariant::factory()->create(['product_id' => $product->id, 'is_default' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'is_default' => false]);

    expect($product->defaultVariant->id)->toBe($default->id);
});

it('defaultVariant returns null when no default variant exists', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->create(['product_id' => $product->id, 'is_default' => false]);

    expect($product->defaultVariant)->toBeNull();
});

// --- scopeDraft() ---

it('scopeDraft returns only draft products', function () {
    Product::factory()->create(['status' => ProductStatus::Draft]);
    Product::factory()->create(['status' => ProductStatus::Draft]);
    Product::factory()->create(['status' => ProductStatus::Published]);

    expect(Product::draft()->count())->toBe(2);
});

// --- scopeArchived() ---

it('scopeArchived returns only archived products', function () {
    Product::factory()->create(['status' => ProductStatus::Archived]);
    Product::factory()->create(['status' => ProductStatus::Draft]);
    Product::factory()->create(['status' => ProductStatus::Published]);

    expect(Product::archived()->count())->toBe(1);
});
