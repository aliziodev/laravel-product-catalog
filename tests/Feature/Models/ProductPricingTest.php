<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Aliziodev\ProductCatalog\Models\InventoryItem;
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;

// --- Price range helpers ---

it('minPrice returns null when product has no active variants', function () {
    $product = Product::factory()->create();

    expect($product->minPrice())->toBeNull();
});

it('minPrice returns lowest active variant price', function () {
    $product = Product::factory()->create();

    ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 50000, 'is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 75000, 'is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 30000, 'is_active' => false]);

    expect($product->minPrice())->toBe(50000.0);
});

it('maxPrice returns highest active variant price', function () {
    $product = Product::factory()->create();

    ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 50000, 'is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 75000, 'is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 100000, 'is_active' => false]);

    expect($product->maxPrice())->toBe(75000.0);
});

it('priceRange returns null when no active variants', function () {
    $product = Product::factory()->create();

    expect($product->priceRange())->toBeNull();
});

it('priceRange returns min and max for active variants', function () {
    $product = Product::factory()->create();

    ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 50000, 'is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 75000, 'is_active' => true]);

    $range = $product->priceRange();

    expect($range)->toBeArray()
        ->and($range['min'])->toBe(50000.0)
        ->and($range['max'])->toBe(75000.0);
});

it('priceRange min equals max for single variant', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 60000, 'is_active' => true]);

    $range = $product->priceRange();

    expect($range['min'])->toBe($range['max']);
});

// --- scopeInStock ---

it('scopeInStock includes product with allow policy variant', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'policy' => InventoryPolicy::Allow, 'quantity' => 0]);

    expect(Product::inStock()->find($product->id))->not->toBeNull();
});

it('scopeInStock includes product with track policy and available stock', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'policy' => InventoryPolicy::Track, 'quantity' => 10, 'reserved_quantity' => 5]);

    expect(Product::inStock()->find($product->id))->not->toBeNull();
});

it('scopeInStock excludes product with deny policy', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'policy' => InventoryPolicy::Deny, 'quantity' => 100]);

    expect(Product::inStock()->find($product->id))->toBeNull();
});

it('scopeInStock excludes product with track policy and zero available quantity', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'policy' => InventoryPolicy::Track, 'quantity' => 5, 'reserved_quantity' => 5]);

    expect(Product::inStock()->find($product->id))->toBeNull();
});

it('scopeInStock is satisfied by any one active variant in stock', function () {
    $product = Product::factory()->create();

    $out = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
    InventoryItem::factory()->create(['variant_id' => $out->id, 'policy' => InventoryPolicy::Track, 'quantity' => 0, 'reserved_quantity' => 0]);

    $in = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
    InventoryItem::factory()->create(['variant_id' => $in->id, 'policy' => InventoryPolicy::Allow, 'quantity' => 0]);

    expect(Product::inStock()->find($product->id))->not->toBeNull();
});
