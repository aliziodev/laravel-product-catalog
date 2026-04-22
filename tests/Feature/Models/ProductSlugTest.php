<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('auto-generates route_key and slug on create', function () {
    $product = Product::factory()->create(['name' => 'Kaos Premium']);

    expect($product->route_key)->not->toBeEmpty()
        ->and($product->slug)->toStartWith('kaos-premium-')
        ->and($product->slug)->toEndWith($product->route_key);
});

it('route_key length matches config default of 8', function () {
    $product = Product::factory()->create(['name' => 'Test']);

    expect(strlen($product->route_key))->toBe(8);
});

it('route_key length respects custom config', function () {
    config(['product-catalog.slug.route_key_length' => 6]);

    $product = Product::factory()->create(['name' => 'Test']);

    expect(strlen($product->route_key))->toBe(6);
});

it('route_key contains only lowercase alphanumeric characters', function () {
    $product = Product::factory()->create(['name' => 'Test Product']);

    expect($product->route_key)->toMatch('/^[a-z0-9]+$/');
});

it('does not override explicitly set slug on create', function () {
    $product = Product::factory()->create([
        'name' => 'Kaos Premium',
        'slug' => 'my-custom-slug',
    ]);

    expect($product->slug)->toBe('my-custom-slug')
        ->and($product->route_key)->not->toBeEmpty();
});

it('regenerates slug prefix when name changes', function () {
    $product = Product::factory()->create(['name' => 'Old Name']);
    $originalRouteKey = $product->route_key;

    $product->update(['name' => 'New Name']);

    expect($product->slug)->toStartWith('new-name-')
        ->and($product->route_key)->toBe($originalRouteKey);
});

it('route_key never changes across multiple name updates', function () {
    $product = Product::factory()->create(['name' => 'First Name']);
    $key = $product->route_key;

    $product->update(['name' => 'Second Name']);
    $product->update(['name' => 'Third Name']);

    expect($product->fresh()->route_key)->toBe($key);
});

it('does not regenerate slug when slug is explicitly set on update', function () {
    $product = Product::factory()->create(['name' => 'Original Name']);

    $product->update(['name' => 'New Name', 'slug' => 'manual-override']);

    expect($product->slug)->toBe('manual-override');
});

it('does not regenerate slug when name is not dirty', function () {
    $product = Product::factory()->create(['name' => 'Same Name']);
    $originalSlug = $product->slug;

    $product->update(['meta_title' => 'Some Meta']);

    expect($product->slug)->toBe($originalSlug);
});

it('route_key is unique across products', function () {
    $products = Product::factory()->count(20)->create();
    $keys = $products->pluck('route_key')->unique();

    expect($keys)->toHaveCount(20);
});

it('slug is unique across products because route_key is unique', function () {
    // Two products with the same name get different slugs via unique route_key
    $a = Product::factory()->create(['name' => 'Same Name']);
    $b = Product::factory()->create(['name' => 'Same Name']);

    expect($a->slug)->not->toBe($b->slug)
        ->and($a->route_key)->not->toBe($b->route_key);
});

it('new product with same name as soft-deleted product gets a different slug', function () {
    $original = Product::factory()->create(['name' => 'Unique Item']);
    $originalSlug = $original->slug;
    $original->delete(); // soft delete

    $replacement = Product::factory()->create(['name' => 'Unique Item']);

    expect($replacement->slug)->not->toBe($originalSlug);
});

it('does not regenerate slug when auto_generate is disabled', function () {
    config(['product-catalog.slug.auto_generate' => false]);

    $product = Product::factory()->create(['name' => 'Original Name']);
    $originalSlug = $product->slug;

    $product->update(['name' => 'Changed Name']);

    expect($product->slug)->toBe($originalSlug);
});

it('clamps route_key_length to minimum 4 when set to 0', function () {
    config(['product-catalog.slug.route_key_length' => 0]);

    $product = Product::factory()->create(['name' => 'Test']);

    expect(strlen($product->route_key))->toBe(4);
});

it('clamps route_key_length to minimum 4 when set to a negative number', function () {
    config(['product-catalog.slug.route_key_length' => -10]);

    $product = Product::factory()->create(['name' => 'Test']);

    expect(strlen($product->route_key))->toBe(4);
});

it('clamps route_key_length to maximum 32 when set above 32', function () {
    config(['product-catalog.slug.route_key_length' => 100]);

    $product = Product::factory()->create(['name' => 'Test']);

    expect(strlen($product->route_key))->toBe(32);
});

// --- extractRouteKey ---

it('extractRouteKey returns the last segment after the final dash', function () {
    expect(Product::extractRouteKey('kaos-premium-abc12345'))->toBe('abc12345');
});

it('extractRouteKey returns the full string when there is no dash', function () {
    expect(Product::extractRouteKey('abc12345'))->toBe('abc12345');
});

it('extractRouteKey handles multiple dashes correctly', function () {
    expect(Product::extractRouteKey('kaos-pria-dewasa-premium-xyz99'))->toBe('xyz99');
});

// --- scopeBySlug / findBySlug / findBySlugOrFail ---

it('scopeBySlug finds a product via its route_key suffix', function () {
    $product = Product::factory()->create(['name' => 'Kaos Premium']);

    $found = Product::bySlug($product->slug)->first();

    expect($found->id)->toBe($product->id);
});

it('scopeBySlug finds a product even when the name prefix in the slug has changed', function () {
    $product = Product::factory()->create(['name' => 'Old Name']);
    $originalSlug = $product->slug;

    $product->update(['name' => 'New Name']);

    // Old slug still resolves because route_key (suffix) is unchanged
    $found = Product::bySlug($originalSlug)->first();

    expect($found->id)->toBe($product->id);
});

it('scopeBySlug finds a product by exact manual slug', function () {
    $product = Product::factory()->create(['slug' => 'my-manual-slug']);

    $found = Product::bySlug('my-manual-slug')->first();

    expect($found->id)->toBe($product->id);
});

it('findBySlug returns the product when found', function () {
    $product = Product::factory()->create(['name' => 'Test Product']);

    expect(Product::findBySlug($product->slug)?->id)->toBe($product->id);
});

it('findBySlug returns null when slug does not match any product', function () {
    expect(Product::findBySlug('does-not-exist-zzzzzz'))->toBeNull();
});

it('findBySlugOrFail throws ModelNotFoundException when slug does not match', function () {
    Product::findBySlugOrFail('does-not-exist-zzzzzz');
})->throws(ModelNotFoundException::class);
