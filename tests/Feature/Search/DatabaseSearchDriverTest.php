<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Aliziodev\ProductCatalog\Enums\ProductStatus;
use Aliziodev\ProductCatalog\Enums\ProductType;
use Aliziodev\ProductCatalog\Models\Brand;
use Aliziodev\ProductCatalog\Models\Category;
use Aliziodev\ProductCatalog\Models\InventoryItem;
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Aliziodev\ProductCatalog\Models\Tag;
use Aliziodev\ProductCatalog\Search\DatabaseSearchDriver;

// ── helpers ──────────────────────────────────────────────────────────────────

function makePublishedProduct(array $attrs = []): Product
{
    return Product::factory()->create(array_merge(['status' => ProductStatus::Published], $attrs));
}

function makeVariantWithPrice(Product $product, float $price): ProductVariant
{
    return ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => $price,
        'is_active' => true,
    ]);
}

// ── text search ───────────────────────────────────────────────────────────────

it('returns products matching the query by name', function () {
    makePublishedProduct(['name' => 'Kemeja Batik']);
    makePublishedProduct(['name' => 'Celana Jeans']);

    $result = app(DatabaseSearchDriver::class)->paginate('kemeja', [], 15, 1);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->name)->toBe('Kemeja Batik');
});

it('returns all published products when query is empty', function () {
    makePublishedProduct();
    makePublishedProduct();
    Product::factory()->create(['status' => ProductStatus::Draft]);

    $result = app(DatabaseSearchDriver::class)->paginate('', [], 15, 1);

    expect($result->total())->toBe(2);
});

it('excludes draft products by default', function () {
    Product::factory()->create(['name' => 'Draft Product', 'status' => ProductStatus::Draft]);

    $result = app(DatabaseSearchDriver::class)->paginate('draft', [], 15, 1);

    expect($result->total())->toBe(0);
});

it('includes draft products when status filter is set to draft', function () {
    Product::factory()->create(['name' => 'Hidden Draft', 'status' => ProductStatus::Draft]);

    $result = app(DatabaseSearchDriver::class)->paginate('hidden', ['status' => 'draft'], 15, 1);

    expect($result->total())->toBe(1);
});

// ── category filter ───────────────────────────────────────────────────────────

it('filters by category slug via pivot table', function () {
    $cat = Category::factory()->create(['slug' => 'shirts']);
    $match = makePublishedProduct();
    $match->categories()->attach($cat);

    makePublishedProduct(); // no category

    $result = app(DatabaseSearchDriver::class)->paginate('', ['category' => 'shirts'], 15, 1);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($match->id);
});

it('filters by category ID via primary_category_id', function () {
    $cat = Category::factory()->create();
    $match = makePublishedProduct(['primary_category_id' => $cat->id]);

    makePublishedProduct();

    $result = app(DatabaseSearchDriver::class)->paginate('', ['category' => $cat->id], 15, 1);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($match->id);
});

it('returns empty when category slug does not exist', function () {
    makePublishedProduct();

    $result = app(DatabaseSearchDriver::class)->paginate('', ['category' => 'non-existent'], 15, 1);

    expect($result->total())->toBe(0);
});

// ── brand filter ──────────────────────────────────────────────────────────────

it('filters by brand slug', function () {
    $brand = Brand::factory()->create(['slug' => 'techco']);
    $match = makePublishedProduct(['brand_id' => $brand->id]);

    makePublishedProduct(); // no brand

    $result = app(DatabaseSearchDriver::class)->paginate('', ['brand' => 'techco'], 15, 1);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($match->id);
});

it('filters by brand ID', function () {
    $brand = Brand::factory()->create();
    $match = makePublishedProduct(['brand_id' => $brand->id]);

    makePublishedProduct();

    $result = app(DatabaseSearchDriver::class)->paginate('', ['brand' => $brand->id], 15, 1);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($match->id);
});

// ── tag filter ────────────────────────────────────────────────────────────────

it('filters by tag slug (AND logic for multiple tags)', function () {
    $sale = Tag::factory()->create(['slug' => 'sale']);
    $new = Tag::factory()->create(['slug' => 'new-arrival']);

    $both = makePublishedProduct();
    $both->tags()->attach([$sale->id, $new->id]);

    $onlyOne = makePublishedProduct();
    $onlyOne->tags()->attach($sale);

    // both tags → only one product
    $result = app(DatabaseSearchDriver::class)->paginate('', ['tags' => ['sale', 'new-arrival']], 15, 1);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($both->id);
});

// ── price range filter ────────────────────────────────────────────────────────

it('filters by minimum price', function () {
    $cheap = makePublishedProduct();
    $expensive = makePublishedProduct();

    makeVariantWithPrice($cheap, 50_000);
    makeVariantWithPrice($expensive, 500_000);

    $result = app(DatabaseSearchDriver::class)->paginate('', ['min_price' => 100_000], 15, 1);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($expensive->id);
});

it('filters by maximum price', function () {
    $cheap = makePublishedProduct();
    $expensive = makePublishedProduct();

    makeVariantWithPrice($cheap, 50_000);
    makeVariantWithPrice($expensive, 500_000);

    $result = app(DatabaseSearchDriver::class)->paginate('', ['max_price' => 100_000], 15, 1);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($cheap->id);
});

it('filters by price range', function () {
    $cheap = makePublishedProduct();
    $mid = makePublishedProduct();
    $expensive = makePublishedProduct();

    makeVariantWithPrice($cheap, 10_000);
    makeVariantWithPrice($mid, 150_000);
    makeVariantWithPrice($expensive, 900_000);

    $result = app(DatabaseSearchDriver::class)->paginate('', ['min_price' => 100_000, 'max_price' => 200_000], 15, 1);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($mid->id);
});

// ── in_stock filter ───────────────────────────────────────────────────────────

it('filters by in_stock returns only stocked products', function () {
    $inStock = makePublishedProduct();
    $outStock = makePublishedProduct();

    $varIn = makeVariantWithPrice($inStock, 100_000);
    $varOut = makeVariantWithPrice($outStock, 100_000);

    InventoryItem::factory()->create(['variant_id' => $varIn->id,  'quantity' => 10, 'reserved_quantity' => 0,  'policy' => InventoryPolicy::Track]);
    InventoryItem::factory()->create(['variant_id' => $varOut->id, 'quantity' => 0,  'reserved_quantity' => 0,  'policy' => InventoryPolicy::Track]);

    $result = app(DatabaseSearchDriver::class)->paginate('', ['in_stock' => true], 15, 1);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($inStock->id);
});

// ── product type filter ───────────────────────────────────────────────────────

it('filters by product type', function () {
    makePublishedProduct(['type' => ProductType::Simple]);
    makePublishedProduct(['type' => ProductType::Simple]);
    makePublishedProduct(['type' => ProductType::Variable]);

    $result = app(DatabaseSearchDriver::class)->paginate('', ['type' => 'variable'], 15, 1);

    expect($result->total())->toBe(1);
});

// ── sort ──────────────────────────────────────────────────────────────────────

it('sorts by name ascending', function () {
    makePublishedProduct(['name' => 'Zebra']);
    makePublishedProduct(['name' => 'Apple']);
    makePublishedProduct(['name' => 'Mango']);

    $result = app(DatabaseSearchDriver::class)->paginate('', ['sort_by' => 'name', 'sort_direction' => 'asc'], 15, 1);

    $names = collect($result->items())->pluck('name')->all();
    expect($names)->toBe(['Apple', 'Mango', 'Zebra']);
});

it('sorts by oldest (published_at ascending)', function () {
    $first = makePublishedProduct(['published_at' => now()->subDays(10)]);
    $second = makePublishedProduct(['published_at' => now()->subDays(5)]);
    $third = makePublishedProduct(['published_at' => now()->subDays(1)]);

    $result = app(DatabaseSearchDriver::class)->paginate('', ['sort_by' => 'oldest'], 15, 1);

    $ids = collect($result->items())->pluck('id')->all();
    expect($ids)->toBe([$first->id, $second->id, $third->id]);
});

it('sorts by price ascending', function () {
    $a = makePublishedProduct();
    $b = makePublishedProduct();
    $c = makePublishedProduct();

    makeVariantWithPrice($a, 300_000);
    makeVariantWithPrice($b, 100_000);
    makeVariantWithPrice($c, 200_000);

    $result = app(DatabaseSearchDriver::class)->paginate('', ['sort_by' => 'price', 'sort_direction' => 'asc'], 15, 1);

    $ids = collect($result->items())->pluck('id')->all();
    expect($ids)->toBe([$b->id, $c->id, $a->id]);
});

it('uses the fulltext search code path when search.fulltext config is true', function () {
    config(['product-catalog.search.fulltext' => true]);
    makePublishedProduct(['name' => 'Kemeja Batik']);

    // SQLite does not support FULLTEXT — the Throwable that propagates proves
    // the fulltext branch (lines 132–143) was entered, not the LIKE branch.
    expect(fn () => app(DatabaseSearchDriver::class)->paginate('kemeja', [], 15, 1))
        ->toThrow(RuntimeException::class, 'This database engine does not support fulltext search operations.');
});

// ── get() ─────────────────────────────────────────────────────────────────────

it('get() returns a collection without pagination', function () {
    makePublishedProduct(['name' => 'Laptop']);
    makePublishedProduct(['name' => 'Phone']);

    $result = app(DatabaseSearchDriver::class)->get('laptop', []);

    expect($result)->toHaveCount(1)
        ->and($result->first()->name)->toBe('Laptop');
});

// ── pagination ────────────────────────────────────────────────────────────────

it('paginates correctly', function () {
    for ($i = 1; $i <= 5; $i++) {
        makePublishedProduct(['name' => "Product {$i}"]);
    }

    $page1 = app(DatabaseSearchDriver::class)->paginate('', [], 3, 1);
    $page2 = app(DatabaseSearchDriver::class)->paginate('', [], 3, 2);

    expect($page1->total())->toBe(5)
        ->and($page1->items())->toHaveCount(3)
        ->and($page2->items())->toHaveCount(2);
});
