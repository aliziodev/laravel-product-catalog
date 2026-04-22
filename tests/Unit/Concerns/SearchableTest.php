<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Concerns\Searchable;
use Aliziodev\ProductCatalog\Enums\ProductStatus;
use Aliziodev\ProductCatalog\Enums\ProductType;
use Aliziodev\ProductCatalog\Models\Brand;
use Aliziodev\ProductCatalog\Models\Category;
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Aliziodev\ProductCatalog\Models\Tag;

/**
 * Concrete test double: base Product model extended with the Searchable concern.
 * Required because the package's Product model intentionally does not couple to Scout.
 */
class SearchableProduct extends Product
{
    use Searchable;
}

// ── toSearchableArray() — structure ───────────────────────────────────────────

it('toSearchableArray() contains all expected top-level keys', function () {
    $product = Product::factory()->create([
        'status' => ProductStatus::Published,
        'type' => ProductType::Simple,
    ]);

    $arr = (new SearchableProduct)->forceFill($product->attributesToArray())->toSearchableArray();

    expect($arr)->toHaveKeys([
        'id', 'name', 'code', 'slug',
        'short_description', 'description',
        'type', 'status',
        'brand_name',
        'primary_category_name', 'primary_category_slug',
        'categories', 'category_slugs',
        'tags',
        'skus', 'min_price',
        'published_at',
    ]);
});

it('toSearchableArray() casts type and status to their scalar values', function () {
    $product = Product::factory()->create([
        'status' => ProductStatus::Published,
        'type' => ProductType::Simple,
    ]);

    $arr = (new SearchableProduct)->forceFill($product->attributesToArray())->toSearchableArray();

    expect($arr['status'])->toBe(ProductStatus::Published->value)
        ->and($arr['type'])->toBe(ProductType::Simple->value);
});

// ── toSearchableArray() — relations not loaded ────────────────────────────────

it('returns null brand_name when brand relation is not loaded', function () {
    $product = Product::factory()->create();

    $arr = SearchableProduct::find($product->id)->toSearchableArray();

    expect($arr['brand_name'])->toBeNull();
});

it('returns null primary_category fields when primaryCategory relation is not loaded', function () {
    $product = Product::factory()->create();

    $arr = SearchableProduct::find($product->id)->toSearchableArray();

    expect($arr['primary_category_name'])->toBeNull()
        ->and($arr['primary_category_slug'])->toBeNull();
});

it('returns empty arrays for categories, tags, skus when relations are not loaded', function () {
    $product = Product::factory()->create();

    $arr = SearchableProduct::find($product->id)->toSearchableArray();

    expect($arr['categories'])->toBe([])
        ->and($arr['category_slugs'])->toBe([])
        ->and($arr['tags'])->toBe([])
        ->and($arr['skus'])->toBe([]);
});

it('returns null min_price when variants relation is not loaded', function () {
    $product = Product::factory()->create();

    $arr = SearchableProduct::find($product->id)->toSearchableArray();

    expect($arr['min_price'])->toBeNull();
});

// ── toSearchableArray() — relations loaded ────────────────────────────────────

it('returns brand_name when brand relation is loaded', function () {
    $brand = Brand::factory()->create(['name' => 'Acme']);
    $product = Product::factory()->create(['brand_id' => $brand->id]);

    $arr = SearchableProduct::with('brand')->find($product->id)->toSearchableArray();

    expect($arr['brand_name'])->toBe('Acme');
});

it('returns primary_category fields when primaryCategory relation is loaded', function () {
    $cat = Category::factory()->create(['name' => 'Gadgets', 'slug' => 'gadgets']);
    $product = Product::factory()->create(['primary_category_id' => $cat->id]);

    $arr = SearchableProduct::with('primaryCategory')->find($product->id)->toSearchableArray();

    expect($arr['primary_category_name'])->toBe('Gadgets')
        ->and($arr['primary_category_slug'])->toBe('gadgets');
});

it('returns categories and category_slugs arrays when categories relation is loaded', function () {
    $cat = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
    $product = Product::factory()->create();
    $product->categories()->attach($cat);

    $arr = SearchableProduct::with('categories')->find($product->id)->toSearchableArray();

    expect($arr['categories'])->toBe(['Electronics'])
        ->and($arr['category_slugs'])->toBe(['electronics']);
});

it('returns tag slugs array when tags relation is loaded', function () {
    $tag = Tag::factory()->create(['slug' => 'featured']);
    $product = Product::factory()->create();
    $product->tags()->attach($tag);

    $arr = SearchableProduct::with('tags')->find($product->id)->toSearchableArray();

    expect($arr['tags'])->toBe(['featured']);
});

it('returns skus from variants when variants relation is loaded', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'SKU-001', 'is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'SKU-002', 'is_active' => true]);

    // Load variants via the base Product model (avoids SearchableProduct FK derivation issue)
    // then transfer the already-loaded relation to the searchable instance via setRelation().
    $loaded = Product::with('variants')->find($product->id);
    $searchable = SearchableProduct::find($product->id);
    $searchable->setRelation('variants', $loaded->variants);

    $arr = $searchable->toSearchableArray();

    expect($arr['skus'])->toContain('SKU-001')
        ->and($arr['skus'])->toContain('SKU-002');
});

it('min_price only considers active variants', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 50_000, 'is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 10_000, 'is_active' => false]);

    $loaded = Product::with('variants')->find($product->id);
    $searchable = SearchableProduct::find($product->id);
    $searchable->setRelation('variants', $loaded->variants);

    $arr = $searchable->toSearchableArray();

    expect($arr['min_price'])->toBe(50_000.0);
});

// ── makeAllSearchableUsing() ──────────────────────────────────────────────────

it('makeAllSearchableUsing() adds all required relations to the builder', function () {
    $instance = new SearchableProduct;
    $query = SearchableProduct::query();

    $result = $instance->makeAllSearchableUsing($query);

    $eagerLoads = array_keys($result->getEagerLoads());

    expect($eagerLoads)->toContain('brand')
        ->and($eagerLoads)->toContain('primaryCategory')
        ->and($eagerLoads)->toContain('categories')
        ->and($eagerLoads)->toContain('tags')
        ->and($eagerLoads)->toContain('variants');
});
