<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;

it('scopeSearch finds product by name', function () {
    Product::factory()->create(['name' => 'Kaos Premium Pria']);
    Product::factory()->create(['name' => 'Celana Jeans Slim']);

    $results = Product::search('Kaos')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Kaos Premium Pria');
});

it('scopeSearch finds product by code', function () {
    Product::factory()->create(['name' => 'Product A', 'code' => 'KP-001']);
    Product::factory()->create(['name' => 'Product B', 'code' => 'CJ-002']);

    expect(Product::search('KP-001')->count())->toBe(1);
});

it('scopeSearch finds product by variant sku', function () {
    $product = Product::factory()->create(['name' => 'Sepatu Lari']);
    ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'SHOE-RED-42']);
    Product::factory()->create(['name' => 'Tas Ransel']);

    $results = Product::search('SHOE-RED')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($product->id);
});

it('scopeSearch finds product by short_description', function () {
    Product::factory()->create(['name' => 'A', 'short_description' => 'Bahan katun premium']);
    Product::factory()->create(['name' => 'B', 'short_description' => 'Polyester ringan']);

    expect(Product::search('katun')->count())->toBe(1);
});

it('scopeSearch returns empty when no match', function () {
    Product::factory()->count(3)->create();

    expect(Product::search('zzznomatch')->count())->toBe(0);
});

it('scopeSearch is case insensitive', function () {
    Product::factory()->create(['name' => 'Kaos Premium']);

    expect(Product::search('kaos')->count())->toBe(1);
    expect(Product::search('KAOS')->count())->toBe(1);
});

it('scopeSearch can be combined with other scopes', function () {
    Product::factory()->published()->create(['name' => 'Kaos Published']);
    Product::factory()->create(['name' => 'Kaos Draft']);

    $results = Product::published()->search('Kaos')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Kaos Published');
});
