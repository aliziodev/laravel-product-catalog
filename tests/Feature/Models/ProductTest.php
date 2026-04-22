<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Enums\ProductStatus;
use Aliziodev\ProductCatalog\Enums\ProductType;
use Aliziodev\ProductCatalog\Events\ProductArchived;
use Aliziodev\ProductCatalog\Events\ProductPublished;
use Aliziodev\ProductCatalog\Models\Category;
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Support\Facades\Event;

it('creates a product with default draft status', function () {
    $product = Product::factory()->create(['name' => 'Test Shirt']);

    expect($product->status)->toBe(ProductStatus::Draft)
        ->and($product->isDraft())->toBeTrue()
        ->and($product->isPublished())->toBeFalse();
});

it('publishes a product and fires event', function () {
    $product = Product::factory()->create();

    Event::fake([ProductPublished::class]);
    $product->publish();

    expect($product->fresh()->status)->toBe(ProductStatus::Published)
        ->and($product->fresh()->published_at)->not->toBeNull();

    Event::assertDispatched(ProductPublished::class, fn ($e) => $e->product->is($product));
});

it('does not overwrite published_at on re-publish', function () {
    $product = Product::factory()->create();
    $product->publish();
    $first = $product->fresh()->published_at;

    $product->publish();

    expect($product->fresh()->published_at->eq($first))->toBeTrue();
});

it('archives a product and fires event', function () {
    $product = Product::factory()->published()->create();

    Event::fake([ProductArchived::class]);
    $product->archive();

    expect($product->fresh()->isArchived())->toBeTrue();
    Event::assertDispatched(ProductArchived::class);
});

it('unpublishes a product back to draft', function () {
    $product = Product::factory()->published()->create();
    $product->unpublish();

    expect($product->fresh()->isDraft())->toBeTrue();
});

it('scopes published products correctly', function () {
    Product::factory()->published()->create();
    Product::factory()->draft()->create();
    Product::factory()->archived()->create();

    expect(Product::published()->count())->toBe(1);
});

it('knows when it is a variable product', function () {
    $simple = Product::factory()->create(['type' => ProductType::Simple]);
    $variable = Product::factory()->variable()->create();

    expect($simple->isSimple())->toBeTrue()
        ->and($simple->isVariable())->toBeFalse()
        ->and($variable->isVariable())->toBeTrue();
});

it('has many variants', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->count(3)->create(['product_id' => $product->id]);

    expect($product->variants()->count())->toBe(3);
});

it('can be assigned to categories', function () {
    $product = Product::factory()->create();
    $category = Category::factory()->create();

    $product->categories()->attach($category);

    expect($product->categories()->count())->toBe(1);
});

it('can have a primary category', function () {
    $primary = Category::factory()->create();
    $product = Product::factory()->create(['primary_category_id' => $primary->id]);

    expect($product->primaryCategory->is($primary))->toBeTrue();
});

it('primary category is independent from categories pivot', function () {
    $primary = Category::factory()->create();
    $other = Category::factory()->create();
    $product = Product::factory()->create(['primary_category_id' => $primary->id]);

    $product->categories()->attach([$primary->id, $other->id]);

    expect($product->primaryCategory->is($primary))->toBeTrue()
        ->and($product->categories()->count())->toBe(2);
});

it('primary category is optional', function () {
    $product = Product::factory()->create(['primary_category_id' => null]);

    expect($product->primaryCategory)->toBeNull();
});
