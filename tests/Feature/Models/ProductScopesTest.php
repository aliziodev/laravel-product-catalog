<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Aliziodev\ProductCatalog\Enums\ProductStatus;
use Aliziodev\ProductCatalog\Models\Brand;
use Aliziodev\ProductCatalog\Models\InventoryItem;
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Aliziodev\ProductCatalog\Models\Tag;

// --- scopePublished() ---

it('scopePublished returns only published products', function () {
    Product::factory()->create(['status' => ProductStatus::Published]);
    Product::factory()->create(['status' => ProductStatus::Published]);
    Product::factory()->create(['status' => ProductStatus::Draft]);
    Product::factory()->create(['status' => ProductStatus::Archived]);

    expect(Product::published()->count())->toBe(2);
});

// --- scopeForBrand() ---

it('scopeForBrand filters by brand ID', function () {
    $brand = Brand::factory()->create();
    Product::factory()->create(['brand_id' => $brand->id]);
    Product::factory()->create(['brand_id' => $brand->id]);
    Product::factory()->create(); // no brand

    expect(Product::forBrand($brand->id)->count())->toBe(2);
});

it('scopeForBrand accepts a Brand model instance', function () {
    $brand = Brand::factory()->create();
    Product::factory()->create(['brand_id' => $brand->id]);
    Product::factory()->create();

    expect(Product::forBrand($brand)->count())->toBe(1);
});

// --- scopeWithTag() ---

it('scopeWithTag filters by tag ID', function () {
    $tag = Tag::factory()->create();
    $match = Product::factory()->create();
    $match->tags()->attach($tag);

    Product::factory()->create(); // no tag

    expect(Product::withTag($tag->id)->count())->toBe(1);
});

it('scopeWithTag accepts a Tag model instance', function () {
    $tag = Tag::factory()->create();
    $match = Product::factory()->create();
    $match->tags()->attach($tag);

    Product::factory()->create();

    expect(Product::withTag($tag)->count())->toBe(1);
});

// --- scopeSearch() ---

it('scopeSearch matches by product name', function () {
    Product::factory()->create(['name' => 'Wireless Keyboard']);
    Product::factory()->create(['name' => 'Gaming Mouse']);

    expect(Product::search('keyboard')->count())->toBe(1);
});

it('scopeSearch matches by product code', function () {
    Product::factory()->create(['code' => 'KB-001']);
    Product::factory()->create(['code' => 'MS-002']);

    expect(Product::search('KB')->count())->toBe(1);
});

it('scopeSearch matches by variant SKU', function () {
    $product = Product::factory()->create(['name' => 'Headset Pro']);
    ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'HS-RED-L']);

    Product::factory()->create(['name' => 'Other Product']);

    expect(Product::search('HS-RED')->count())->toBe(1);
});

// --- scopeInStock() ---

it('scopeInStock returns products with at least one active in-stock variant', function () {
    $inStock = Product::factory()->create(['status' => ProductStatus::Published]);
    $outStock = Product::factory()->create(['status' => ProductStatus::Published]);

    $varIn = ProductVariant::factory()->create(['product_id' => $inStock->id, 'is_active' => true]);
    $varOut = ProductVariant::factory()->create(['product_id' => $outStock->id, 'is_active' => true]);

    InventoryItem::factory()->create(['variant_id' => $varIn->id, 'quantity' => 5, 'reserved_quantity' => 0, 'policy' => InventoryPolicy::Track]);
    InventoryItem::factory()->create(['variant_id' => $varOut->id, 'quantity' => 0, 'reserved_quantity' => 0, 'policy' => InventoryPolicy::Track]);

    expect(Product::inStock()->count())->toBe(1)
        ->and(Product::inStock()->first()->id)->toBe($inStock->id);
});

it('scopeInStock includes products with Allow policy regardless of quantity', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 0, 'policy' => InventoryPolicy::Allow]);

    expect(Product::inStock()->count())->toBe(1);
});

it('scopeInStock excludes products where all stock is reserved', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
    // 5 total, 5 reserved → 0 available
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 5, 'reserved_quantity' => 5, 'policy' => InventoryPolicy::Track]);

    expect(Product::inStock()->count())->toBe(0);
});

it('scopeInStock excludes inactive variants', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => false]);
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'policy' => InventoryPolicy::Track]);

    expect(Product::inStock()->count())->toBe(0);
});

// --- bySlug() (via HasSlug concern) ---

it('bySlug scope finds a product by its slug', function () {
    $product = Product::factory()->create();

    $found = Product::query()->bySlug($product->slug)->first();

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($product->id);
});

it('bySlug scope returns empty when slug does not match', function () {
    Product::factory()->create();

    $found = Product::query()->bySlug('non-existent-slug-xyz')->first();

    expect($found)->toBeNull();
});

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
