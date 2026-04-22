<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;

// --- product() relationship ---

it('variant belongs to a product', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    expect($variant->product->id)->toBe($product->id);
});

// --- scopeDefault() ---

it('scopeDefault returns only default variants', function () {
    $product = Product::factory()->create();

    ProductVariant::factory()->create(['product_id' => $product->id, 'is_default' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'is_default' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'is_default' => false]);

    expect($product->variants()->default()->count())->toBe(2);
});

it('scopeDefault returns empty when no default variants exist', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->create(['product_id' => $product->id, 'is_default' => false]);

    expect($product->variants()->default()->count())->toBe(0);
});
