<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Contracts\SearchDriverInterface;
use Aliziodev\ProductCatalog\Enums\ProductStatus;
use Aliziodev\ProductCatalog\Models\Brand;
use Aliziodev\ProductCatalog\Models\Category;
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Aliziodev\ProductCatalog\Models\Tag;
use Aliziodev\ProductCatalog\Search\ProductSearchBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

// ── basic usage ───────────────────────────────────────────────────────────────

it('paginate() returns a LengthAwarePaginator', function () {
    Product::factory()->create(['name' => 'Laptop', 'status' => ProductStatus::Published]);

    $result = ProductSearchBuilder::query('laptop')->paginate(15);

    expect($result->total())->toBe(1);
});

it('get() returns a Collection', function () {
    Product::factory()->create(['name' => 'Laptop Pro', 'status' => ProductStatus::Published]);
    Product::factory()->create(['name' => 'Phone', 'status' => ProductStatus::Published]);

    $result = ProductSearchBuilder::query('laptop')->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->name)->toBe('Laptop Pro');
});

it('empty query returns all published products', function () {
    Product::factory()->count(3)->create(['status' => ProductStatus::Published]);
    Product::factory()->create(['status' => ProductStatus::Draft]);

    $result = ProductSearchBuilder::query()->paginate(15);

    expect($result->total())->toBe(3);
});

// ── filter chain ──────────────────────────────────────────────────────────────

it('inCategory() filters by category slug', function () {
    $cat = Category::factory()->create(['slug' => 'gadgets']);
    $match = Product::factory()->create(['status' => ProductStatus::Published]);
    $match->categories()->attach($cat);

    Product::factory()->create(['status' => ProductStatus::Published]);

    $result = ProductSearchBuilder::query()->inCategory('gadgets')->paginate(15);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($match->id);
});

it('withTags() filters by tag slugs', function () {
    $tag = Tag::factory()->create(['slug' => 'featured']);
    $match = Product::factory()->create(['status' => ProductStatus::Published]);
    $match->tags()->attach($tag);

    Product::factory()->create(['status' => ProductStatus::Published]);

    $result = ProductSearchBuilder::query()->withTags(['featured'])->paginate(15);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($match->id);
});

it('forBrand() filters by brand slug', function () {
    $brand = Brand::factory()->create(['slug' => 'apple']);
    $match = Product::factory()->create(['status' => ProductStatus::Published, 'brand_id' => $brand->id]);

    Product::factory()->create(['status' => ProductStatus::Published]);

    $result = ProductSearchBuilder::query()->forBrand('apple')->paginate(15);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($match->id);
});

it('priceBetween() filters by price range', function () {
    $cheap = Product::factory()->create(['status' => ProductStatus::Published]);
    $mid = Product::factory()->create(['status' => ProductStatus::Published]);

    ProductVariant::factory()->create(['product_id' => $cheap->id, 'price' => 10_000, 'is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $mid->id,   'price' => 150_000, 'is_active' => true]);

    $result = ProductSearchBuilder::query()->priceBetween(100_000, 200_000)->paginate(15);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($mid->id);
});

it('minPrice() and maxPrice() can be set independently', function () {
    $cheap = Product::factory()->create(['status' => ProductStatus::Published]);
    $mid = Product::factory()->create(['status' => ProductStatus::Published]);
    $pricey = Product::factory()->create(['status' => ProductStatus::Published]);

    ProductVariant::factory()->create(['product_id' => $cheap->id,  'price' => 5_000, 'is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $mid->id,    'price' => 50_000, 'is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $pricey->id, 'price' => 500_000, 'is_active' => true]);

    $result = ProductSearchBuilder::query()->minPrice(10_000)->maxPrice(100_000)->paginate(15);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($mid->id);
});

it('onlyInStock() is chainable and does not throw', function () {
    $product = Product::factory()->create(['status' => ProductStatus::Published]);

    // No inventory item → excluded by inStock scope
    expect(
        fn () => ProductSearchBuilder::query()->onlyInStock()->paginate(15)
    )->not->toThrow(Exception::class);
});

it('ofType() filters by product type', function () {
    Product::factory()->create(['status' => ProductStatus::Published, 'type' => 'simple']);
    Product::factory()->create(['status' => ProductStatus::Published, 'type' => 'variable']);

    $result = ProductSearchBuilder::query()->ofType('variable')->paginate(15);

    expect($result->total())->toBe(1);
});

it('withStatus() overrides the default published filter', function () {
    Product::factory()->create(['name' => 'Draft Item', 'status' => ProductStatus::Draft]);

    $result = ProductSearchBuilder::query('draft')->withStatus('draft')->paginate(15);

    expect($result->total())->toBe(1);
});

// ── sort chain ────────────────────────────────────────────────────────────────

it('sortBy() and sortAscending() are chainable', function () {
    Product::factory()->create(['name' => 'Zebra', 'status' => ProductStatus::Published]);
    Product::factory()->create(['name' => 'Apple', 'status' => ProductStatus::Published]);

    $result = ProductSearchBuilder::query()->sortBy('name')->sortAscending()->paginate(15);

    expect($result->items()[0]->name)->toBe('Apple');
});

it('sortDescending() is the default direction', function () {
    Product::factory()->create(['name' => 'Zebra', 'status' => ProductStatus::Published]);
    Product::factory()->create(['name' => 'Apple', 'status' => ProductStatus::Published]);

    $result = ProductSearchBuilder::query()->sortBy('name')->sortDescending()->paginate(15);

    expect($result->items()[0]->name)->toBe('Zebra');
});

// ── fromRequest() ────────────────────────────────────────────────────────────

it('fromRequest() maps q param to text query', function () {
    Product::factory()->create(['name' => 'Wireless Mouse', 'status' => ProductStatus::Published]);
    Product::factory()->create(['name' => 'Keyboard', 'status' => ProductStatus::Published]);

    $request = Request::create('/products', 'GET', ['q' => 'wireless']);
    $result = ProductSearchBuilder::fromRequest($request)->paginate(15);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->name)->toBe('Wireless Mouse');
});

it('fromRequest() maps search param as alias for q', function () {
    Product::factory()->create(['name' => 'Gaming Chair', 'status' => ProductStatus::Published]);
    Product::factory()->create(['name' => 'Office Desk', 'status' => ProductStatus::Published]);

    $request = Request::create('/products', 'GET', ['search' => 'gaming']);
    $result = ProductSearchBuilder::fromRequest($request)->paginate(15);

    expect($result->total())->toBe(1);
});

it('fromRequest() maps category param', function () {
    $cat = Category::factory()->create(['slug' => 'peripherals']);
    $match = Product::factory()->create(['status' => ProductStatus::Published]);
    $match->categories()->attach($cat);

    Product::factory()->create(['status' => ProductStatus::Published]);

    $request = Request::create('/products', 'GET', ['category' => 'peripherals']);
    $result = ProductSearchBuilder::fromRequest($request)->paginate(15);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($match->id);
});

it('fromRequest() maps numeric category to integer ID', function () {
    $cat = Category::factory()->create();
    $match = Product::factory()->create(['status' => ProductStatus::Published, 'primary_category_id' => $cat->id]);

    Product::factory()->create(['status' => ProductStatus::Published]);

    $request = Request::create('/products', 'GET', ['category' => (string) $cat->id]);
    $result = ProductSearchBuilder::fromRequest($request)->paginate(15);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($match->id);
});

it('fromRequest() maps brand param', function () {
    $brand = Brand::factory()->create(['slug' => 'logitech']);
    $match = Product::factory()->create(['status' => ProductStatus::Published, 'brand_id' => $brand->id]);

    Product::factory()->create(['status' => ProductStatus::Published]);

    $request = Request::create('/products', 'GET', ['brand' => 'logitech']);
    $result = ProductSearchBuilder::fromRequest($request)->paginate(15);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($match->id);
});

it('fromRequest() maps numeric brand to integer ID', function () {
    $brand = Brand::factory()->create();
    $match = Product::factory()->create(['status' => ProductStatus::Published, 'brand_id' => $brand->id]);

    Product::factory()->create(['status' => ProductStatus::Published]);

    $request = Request::create('/products', 'GET', ['brand' => (string) $brand->id]);
    $result = ProductSearchBuilder::fromRequest($request)->paginate(15);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($match->id);
});

it('fromRequest() maps tags[] array param', function () {
    $tag = Tag::factory()->create(['slug' => 'bestseller']);
    $match = Product::factory()->create(['status' => ProductStatus::Published]);
    $match->tags()->attach($tag);

    Product::factory()->create(['status' => ProductStatus::Published]);

    $request = Request::create('/products', 'GET', ['tags' => ['bestseller']]);
    $result = ProductSearchBuilder::fromRequest($request)->paginate(15);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($match->id);
});

it('fromRequest() maps legacy tag param (singular, by ID)', function () {
    $tag = Tag::factory()->create();
    $match = Product::factory()->create(['status' => ProductStatus::Published]);
    $match->tags()->attach($tag);

    Product::factory()->create(['status' => ProductStatus::Published]);

    $request = Request::create('/products', 'GET', ['tag' => (string) $tag->id]);
    $result = ProductSearchBuilder::fromRequest($request)->paginate(15);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($match->id);
});

it('fromRequest() maps min_price and max_price', function () {
    $cheap = Product::factory()->create(['status' => ProductStatus::Published]);
    $mid = Product::factory()->create(['status' => ProductStatus::Published]);

    ProductVariant::factory()->create(['product_id' => $cheap->id, 'price' => 5_000,   'is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $mid->id,   'price' => 200_000, 'is_active' => true]);

    $request = Request::create('/products', 'GET', ['min_price' => '100000', 'max_price' => '300000']);
    $result = ProductSearchBuilder::fromRequest($request)->paginate(15);

    expect($result->total())->toBe(1)
        ->and($result->items()[0]->id)->toBe($mid->id);
});

it('fromRequest() maps in_stock boolean', function () {
    $product = Product::factory()->create(['status' => ProductStatus::Published]);

    $request = Request::create('/products', 'GET', ['in_stock' => '1']);
    $result = ProductSearchBuilder::fromRequest($request)->paginate(15);

    // No inventory item → excluded by inStock scope
    expect($result->total())->toBe(0);
});

it('fromRequest() maps sort_by and sort_direction', function () {
    Product::factory()->create(['name' => 'Zebra Product', 'status' => ProductStatus::Published]);
    Product::factory()->create(['name' => 'Alpha Product', 'status' => ProductStatus::Published]);

    $request = Request::create('/products', 'GET', ['sort_by' => 'name', 'sort_direction' => 'asc']);
    $result = ProductSearchBuilder::fromRequest($request)->paginate(15);

    expect($result->items()[0]->name)->toBe('Alpha Product');
});

// ── withRelations() ───────────────────────────────────────────────────────────

it('withRelations() overrides the default eager-loaded relations', function () {
    $brand = Brand::factory()->create(['slug' => 'acme']);
    $product = Product::factory()->create(['status' => ProductStatus::Published, 'brand_id' => $brand->id]);

    $result = ProductSearchBuilder::query()
        ->withRelations(['brand'])
        ->get();

    expect($result->first()->relationLoaded('brand'))->toBeTrue()
        ->and($result->first()->relationLoaded('primaryCategory'))->toBeFalse();
});

it('withRelations([]) disables all eager loading', function () {
    Product::factory()->create(['status' => ProductStatus::Published]);

    $result = ProductSearchBuilder::query()
        ->withRelations([])
        ->get();

    expect($result->first()->relationLoaded('brand'))->toBeFalse()
        ->and($result->first()->relationLoaded('primaryCategory'))->toBeFalse();
});

// ── driver override ───────────────────────────────────────────────────────────

it('usingDriver() injects a custom driver instance', function () {
    $fakeDriver = new class implements SearchDriverInterface
    {
        public function paginate(string $query, array $filters, int $perPage, int $page): Illuminate\Contracts\Pagination\LengthAwarePaginator
        {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        public function get(string $query, array $filters): Collection
        {
            return new Collection;
        }
    };

    $result = ProductSearchBuilder::query('anything')->usingDriver($fakeDriver)->paginate(15);

    expect($result->total())->toBe(0);
});
