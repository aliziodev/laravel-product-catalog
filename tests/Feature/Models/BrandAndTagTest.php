<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Models\Brand;
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\Tag;

// --- Brand ---

it('creates a brand', function () {
    $brand = Brand::factory()->create(['name' => 'Nike', 'slug' => 'nike']);

    expect($brand->name)->toBe('Nike')
        ->and($brand->slug)->toBe('nike');
});

it('assigns a brand to a product', function () {
    $brand = Brand::factory()->create();
    $product = Product::factory()->create(['brand_id' => $brand->id]);

    expect($product->brand->is($brand))->toBeTrue();
});

it('product without brand returns null', function () {
    $product = Product::factory()->create(['brand_id' => null]);

    expect($product->brand)->toBeNull();
});

it('scopes products by brand', function () {
    $nike = Brand::factory()->create();
    $adidas = Brand::factory()->create();

    Product::factory()->create(['brand_id' => $nike->id]);
    Product::factory()->create(['brand_id' => $nike->id]);
    Product::factory()->create(['brand_id' => $adidas->id]);

    expect(Product::forBrand($nike)->count())->toBe(2)
        ->and(Product::forBrand($adidas)->count())->toBe(1);
});

it('brand has many products', function () {
    $brand = Brand::factory()->create();
    Product::factory()->count(3)->create(['brand_id' => $brand->id]);

    expect($brand->products()->count())->toBe(3);
});

it('can unassign a brand from a product', function () {
    $brand = Brand::factory()->create();
    $product = Product::factory()->create(['brand_id' => $brand->id]);

    $product->update(['brand_id' => null]);

    expect($product->fresh()->brand_id)->toBeNull()
        ->and($product->fresh()->brand)->toBeNull();
});

// --- Tags ---

it('creates a tag', function () {
    $tag = Tag::factory()->create(['name' => 'summer', 'slug' => 'summer']);

    expect($tag->name)->toBe('summer')
        ->and($tag->slug)->toBe('summer');
});

it('attaches multiple tags to a product', function () {
    $product = Product::factory()->create();
    $tags = Tag::factory()->count(3)->create();

    $product->tags()->attach($tags->pluck('id'));

    expect($product->tags()->count())->toBe(3);
});

it('scopes products by tag', function () {
    [$tagA, $tagB] = Tag::factory()->count(2)->create()->all();

    $p1 = Product::factory()->create();
    $p2 = Product::factory()->create();
    $p3 = Product::factory()->create();

    $p1->tags()->attach($tagA);
    $p2->tags()->attach([$tagA->id, $tagB->id]);
    $p3->tags()->attach($tagB);

    expect(Product::withTag($tagA)->count())->toBe(2)
        ->and(Product::withTag($tagB)->count())->toBe(2);
});

it('tag has many products', function () {
    $tag = Tag::factory()->create();
    $products = Product::factory()->count(2)->create();
    $products->each(fn ($p) => $p->tags()->attach($tag));

    expect($tag->products()->count())->toBe(2);
});

it('detaches tags when product is force deleted', function () {
    $product = Product::factory()->create();
    $tag = Tag::factory()->create();
    $product->tags()->attach($tag);

    $product->forceDelete();

    expect(Tag::find($tag->id))->not->toBeNull(); // tag tetap ada
});

// --- Tag soft delete ---

it('soft deletes a tag', function () {
    $tag = Tag::factory()->create();

    $tag->delete();

    expect(Tag::find($tag->id))->toBeNull()
        ->and(Tag::withTrashed()->find($tag->id))->not->toBeNull();
});

it('restores a soft deleted tag', function () {
    $tag = Tag::factory()->create();
    $tag->delete();

    $tag->restore();

    expect(Tag::find($tag->id))->not->toBeNull();
});

it('force deletes a tag permanently', function () {
    $tag = Tag::factory()->create();
    $tag->delete();

    $tag->forceDelete();

    expect(Tag::withTrashed()->find($tag->id))->toBeNull();
});

it('soft deleted tag is excluded from product tag queries', function () {
    $product = Product::factory()->create();
    $tag = Tag::factory()->create();
    $product->tags()->attach($tag);

    $tag->delete();

    expect($product->tags()->count())->toBe(0);
});
