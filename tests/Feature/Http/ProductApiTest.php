<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Enums\ProductStatus;
use Aliziodev\ProductCatalog\Enums\ProductType;
use Aliziodev\ProductCatalog\Models\Brand;
use Aliziodev\ProductCatalog\Models\Category;
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Aliziodev\ProductCatalog\Models\Tag;

it('returns paginated published products', function () {
    Product::factory()->count(3)->create(['status' => ProductStatus::Published, 'published_at' => now()]);

    $this->getJson('/catalog/products')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data', 'links', 'meta']);
});

it('does not return draft products', function () {
    Product::factory()->create(['status' => ProductStatus::Published, 'slug' => 'pub', 'published_at' => now()]);
    Product::factory()->create(['status' => ProductStatus::Draft, 'slug' => 'draft']);

    $this->getJson('/catalog/products')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('does not return archived products', function () {
    Product::factory()->create(['status' => ProductStatus::Published, 'slug' => 'pub', 'published_at' => now()]);
    Product::factory()->create(['status' => ProductStatus::Archived, 'slug' => 'archived']);

    $this->getJson('/catalog/products')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('ignores the status query parameter', function (ProductStatus $status) {
    $published = Product::factory()->create(['status' => ProductStatus::Published, 'slug' => 'pub', 'published_at' => now()]);
    Product::factory()->create(['status' => $status, 'slug' => 'hidden']);

    $this->getJson('/catalog/products?status='.$status->value)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $published->id);
})->with([
    'draft' => [ProductStatus::Draft],
    'private' => [ProductStatus::Private],
    'archived' => [ProductStatus::Archived],
]);

it('filters products by brand', function () {
    $brand = Brand::factory()->create();
    Product::factory()->create(['status' => ProductStatus::Published, 'slug' => 'branded', 'brand_id' => $brand->id, 'published_at' => now()]);
    Product::factory()->create(['status' => ProductStatus::Published, 'slug' => 'other', 'published_at' => now()]);

    $this->getJson('/catalog/products?brand='.$brand->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'branded');
});

it('filters products by tag', function () {
    $tag = Tag::factory()->create(['slug' => 'sale', 'name' => 'Sale']);
    $tagged = Product::factory()->create(['status' => ProductStatus::Published, 'slug' => 'tagged', 'published_at' => now()]);
    $tagged->tags()->attach($tag);
    Product::factory()->create(['status' => ProductStatus::Published, 'slug' => 'untagged', 'published_at' => now()]);

    $this->getJson('/catalog/products?tag='.$tag->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'tagged');
});

it('filters products by category', function () {
    $category = Category::factory()->create();
    $inCat = Product::factory()->create(['status' => ProductStatus::Published, 'slug' => 'in-cat', 'published_at' => now()]);
    $inCat->categories()->attach($category);
    Product::factory()->create(['status' => ProductStatus::Published, 'slug' => 'out-cat', 'published_at' => now()]);

    $this->getJson('/catalog/products?category='.$category->id)
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters products by primary category', function () {
    $category = Category::factory()->create();
    Product::factory()->create([
        'status' => ProductStatus::Published,
        'slug' => 'primary-cat',
        'primary_category_id' => $category->id,
        'published_at' => now(),
    ]);
    Product::factory()->create(['status' => ProductStatus::Published, 'slug' => 'no-primary', 'published_at' => now()]);

    $this->getJson('/catalog/products?category='.$category->id)
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters products by type', function () {
    Product::factory()->create(['status' => ProductStatus::Published, 'slug' => 'variable', 'type' => ProductType::Variable, 'published_at' => now()]);
    Product::factory()->create(['status' => ProductStatus::Published, 'slug' => 'simple', 'type' => ProductType::Simple, 'published_at' => now()]);

    $this->getJson('/catalog/products?type=variable')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'variable');
});

it('respects per_page limit up to 50', function () {
    Product::factory()->count(10)->create(['status' => ProductStatus::Published, 'published_at' => now()]);

    $this->getJson('/catalog/products?per_page=3')
        ->assertOk()
        ->assertJsonCount(3, 'data');

    $this->getJson('/catalog/products?per_page=200')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 50);
});

it('falls back to the default page size when per_page is below 1 or not a number', function (string $perPage) {
    Product::factory()->count(20)->create(['status' => ProductStatus::Published, 'published_at' => now()]);

    $this->getJson('/catalog/products?per_page='.$perPage)
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.per_page', 15);
})->with(['-1', '0', 'abc']);

it('ignores malformed filter parameters instead of failing', function (string $query) {
    Product::factory()->create(['status' => ProductStatus::Published, 'published_at' => now()]);

    $this->getJson('/catalog/products?'.$query)
        ->assertOk()
        ->assertJsonCount(1, 'data');
})->with([
    'unknown type' => ['type=foo'],
    'type as array' => ['type[]=simple'],
    'sort_by as array' => ['sort_by[]=price'],
    'status as array' => ['status[]=draft'],
    'category as array' => ['category[]=shirts'],
    'brand as array' => ['brand[]=acme'],
    'tag as array' => ['tag[]=sale'],
    'nested tags' => ['tags[][]=sale'],
    'q as array' => ['q[]=shirt'],
    'price as array' => ['min_price[]=10&max_price[]=20'],
    'price not numeric' => ['min_price=cheap'],
]);

it('returns a single product by slug with variants', function () {
    $product = Product::factory()->create([
        'status' => ProductStatus::Published,
        'published_at' => now(),
    ]);

    $this->getJson('/catalog/products/'.$product->slug)
        ->assertOk()
        ->assertJsonPath('data.slug', $product->slug)
        ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'type', 'status', 'variants', 'tags']]);
});

it('returns 404 for unknown route key', function () {
    $this->getJson('/catalog/products/does-not-exist-xxxxxxxx')->assertNotFound();
});

it('returns 404 for slug with trailing dash', function () {
    $this->getJson('/catalog/products/kaos-premium-')->assertNotFound();
});

it('returns 404 for draft product by slug', function () {
    $product = Product::factory()->create(['status' => ProductStatus::Draft]);

    $this->getJson('/catalog/products/'.$product->slug)->assertNotFound();
});

it('finds a product by manually set slug without route_key format', function () {
    $product = Product::factory()->create([
        'status' => ProductStatus::Published,
        'slug' => 'my-manual-slug',
        'published_at' => now(),
    ]);

    $this->getJson('/catalog/products/my-manual-slug')
        ->assertOk()
        ->assertJsonPath('data.slug', 'my-manual-slug');
});

it('product response contains expected resource fields', function () {
    $brand = Brand::factory()->create(['slug' => 'test-brand']);
    $product = Product::factory()->create([
        'brand_id' => $brand->id,
        'status' => ProductStatus::Published,
        'published_at' => now(),
    ]);

    $this->getJson('/catalog/products/'.$product->slug)
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'slug', 'type', 'status', 'description', 'short_description', 'featured_image_path', 'meta_title', 'meta_description', 'published_at', 'brand', 'primary_category', 'tags', 'variants'],
        ]);
});

it('leaves inactive variants out of the product detail', function () {
    $product = Product::factory()->create(['status' => ProductStatus::Published, 'published_at' => now()]);
    $active = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => false]);

    $this->getJson('/catalog/products/'.$product->slug)
        ->assertOk()
        ->assertJsonCount(1, 'data.variants')
        ->assertJsonPath('data.variants.0.id', $active->id);
});

it('leaves product and variant meta out of API responses by default', function () {
    $product = Product::factory()->create([
        'status' => ProductStatus::Published,
        'published_at' => now(),
        'meta' => ['reorder_point' => 10],
    ]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'meta' => ['supplier_code' => 'SUP-001']]);

    $this->getJson('/catalog/products')
        ->assertOk()
        ->assertJsonMissingPath('data.0.meta');

    $this->getJson('/catalog/products/'.$product->slug)
        ->assertOk()
        ->assertJsonMissingPath('data.meta')
        ->assertJsonMissingPath('data.variants.0.meta');
});

it('includes meta when routes.expose_meta is on', function () {
    config(['product-catalog.routes.expose_meta' => true]);

    $product = Product::factory()->create([
        'status' => ProductStatus::Published,
        'published_at' => now(),
        'meta' => ['format' => 'paperback'],
    ]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'meta' => ['pages' => 320]]);

    $this->getJson('/catalog/products/'.$product->slug)
        ->assertOk()
        ->assertJsonPath('data.meta.format', 'paperback')
        ->assertJsonPath('data.variants.0.meta.pages', 320);
});

it('hides products scheduled for a future published_at from the list and detail', function () {
    $live = Product::factory()->create(['status' => ProductStatus::Published, 'published_at' => now()->subHour()]);
    $scheduled = Product::factory()->create(['status' => ProductStatus::Published, 'published_at' => now()->addDay()]);

    $this->getJson('/catalog/products')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $live->id);

    $this->getJson('/catalog/products/'.$scheduled->slug)->assertNotFound();
});
