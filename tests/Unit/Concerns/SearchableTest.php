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
use Illuminate\Database\Eloquent\Builder;

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

// ── toSearchableArray() — product without related records ─────────────────────

it('returns null brand_name when the product has no brand', function () {
    $product = Product::factory()->create();

    $arr = SearchableProduct::find($product->id)->toSearchableArray();

    expect($arr['brand_name'])->toBeNull();
});

it('returns null primary_category fields when the product has no primary category', function () {
    $product = Product::factory()->create();

    $arr = SearchableProduct::find($product->id)->toSearchableArray();

    expect($arr['primary_category_name'])->toBeNull()
        ->and($arr['primary_category_slug'])->toBeNull();
});

it('returns empty arrays for categories, tags, skus when the product has none', function () {
    $product = Product::factory()->create();

    $arr = SearchableProduct::find($product->id)->toSearchableArray();

    expect($arr['categories'])->toBe([])
        ->and($arr['category_slugs'])->toBe([])
        ->and($arr['tags'])->toBe([])
        ->and($arr['skus'])->toBe([]);
});

it('returns null min_price when the product has no variants', function () {
    $product = Product::factory()->create();

    $arr = SearchableProduct::find($product->id)->toSearchableArray();

    expect($arr['min_price'])->toBeNull();
});

it('loads missing relations so a single-model save indexes the full document', function () {
    $brand = Brand::factory()->create(['name' => 'Acme']);
    $category = Category::factory()->create(['name' => 'Gadgets', 'slug' => 'gadgets']);
    $tag = Tag::factory()->create(['slug' => 'featured']);

    $product = Product::factory()->create(['brand_id' => $brand->id, 'primary_category_id' => $category->id]);
    $product->categories()->attach($category);
    $product->tags()->attach($tag);
    ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'SKU-001', 'price' => 25_000, 'is_active' => true]);

    // Scout calls toSearchableArray() on the saved model, with no relations loaded.
    $arr = SearchableProduct::find($product->id)->toSearchableArray();

    expect($arr['brand_name'])->toBe('Acme')
        ->and($arr['primary_category_slug'])->toBe('gadgets')
        ->and($arr['category_slugs'])->toBe(['gadgets'])
        ->and($arr['tags'])->toBe(['featured'])
        ->and($arr['skus'])->toBe(['SKU-001'])
        ->and($arr['min_price'])->toBe(25_000.0);
});

it('does not query relations for a model that is not saved yet', function () {
    $arr = (new SearchableProduct)->forceFill(['name' => 'Draft idea'])->toSearchableArray();

    expect($arr['brand_name'])->toBeNull()
        ->and($arr['skus'])->toBe([]);
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

    $arr = SearchableProduct::with('variants')->find($product->id)->toSearchableArray();

    expect($arr['skus'])->toContain('SKU-001')
        ->and($arr['skus'])->toContain('SKU-002');
});

it('min_price only considers active variants', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 50_000, 'is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 10_000, 'is_active' => false]);

    $arr = SearchableProduct::with('variants')->find($product->id)->toSearchableArray();

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

// ── Combining with Laravel\Scout\Searchable ───────────────────────────────────

/**
 * Stand-in for Laravel\Scout\Searchable (not a dev dependency): it declares the
 * same two methods the package concern overrides, with Scout's signatures.
 */
trait ScoutSearchableStandIn
{
    public function toSearchableArray()
    {
        return ['from' => 'scout'];
    }

    protected function makeAllSearchableUsing(Builder $query)
    {
        return $query;
    }
}

class ScoutReadyProduct extends Product
{
    use ScoutSearchableStandIn, Searchable {
        Searchable::toSearchableArray insteadof ScoutSearchableStandIn;
        Searchable::makeAllSearchableUsing insteadof ScoutSearchableStandIn;
    }
}

it('works next to Scout with the documented insteadof block', function () {
    $product = Product::factory()->create(['name' => 'Kaos Polos']);

    $arr = ScoutReadyProduct::find($product->id)->toSearchableArray();

    expect($arr)->not->toHaveKey('from')
        ->and($arr['name'])->toBe('Kaos Polos');
});

it('only overrides the two Scout methods the docs resolve with insteadof', function () {
    // Every extra method that Scout also defines would be a fatal trait
    // collision for existing installs until they add another insteadof line.
    $methods = collect((new ReflectionClass(Searchable::class))->getMethods())
        ->map(fn (ReflectionMethod $method) => $method->getName())
        ->sort()
        ->values()
        ->all();

    expect($methods)->toBe(['makeAllSearchableUsing', 'toSearchableArray']);
});
