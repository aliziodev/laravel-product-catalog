<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Models\Brand;
use Aliziodev\ProductCatalog\Models\Category;
use Aliziodev\ProductCatalog\Models\InventoryItem;
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Aliziodev\ProductCatalog\Models\Tag;

// --- catalog:install ---

it('catalog:install exits successfully', function () {
    $this->artisan('catalog:install')
        ->assertSuccessful();
});

it('catalog:install --migrate exits successfully', function () {
    $this->artisan('catalog:install', ['--migrate' => true])
        ->assertSuccessful();
});

// --- catalog:seed-demo ---

it('catalog:seed-demo creates expected categories', function () {
    $this->artisan('catalog:seed-demo')->assertSuccessful();

    expect(Category::count())->toBe(5)
        ->and(Category::whereNull('parent_id')->count())->toBe(2);
});

it('catalog:seed-demo creates expected brands', function () {
    $this->artisan('catalog:seed-demo')->assertSuccessful();

    expect(Brand::count())->toBe(2);
    expect(Brand::where('slug', 'techco')->exists())->toBeTrue();
    expect(Brand::where('slug', 'stylehouse')->exists())->toBeTrue();
});

it('catalog:seed-demo creates expected tags', function () {
    $this->artisan('catalog:seed-demo')->assertSuccessful();

    expect(Tag::count())->toBe(4);
});

it('catalog:seed-demo creates 4 products', function () {
    $this->artisan('catalog:seed-demo')->assertSuccessful();

    expect(Product::count())->toBe(4);
});

it('catalog:seed-demo creates variants with inventory items', function () {
    $this->artisan('catalog:seed-demo')->assertSuccessful();

    // smartphone (4) + laptop (1) + tshirt (9) + digital (1) = 15
    expect(ProductVariant::count())->toBe(15)
        ->and(InventoryItem::count())->toBe(15);
});

it('catalog:seed-demo digital product uses allow inventory policy', function () {
    $this->artisan('catalog:seed-demo')->assertSuccessful();

    $digital = Product::where('slug', 'premium-license-key')->first();
    $item = InventoryItem::where('variant_id', $digital->variants->first()->id)->first();

    expect($item->policy->value)->toBe('allow');
});

it('catalog:seed-demo refuses to run in production without --force', function () {
    config(['app.env' => 'production']);

    $this->artisan('catalog:seed-demo')->assertFailed();

    expect(Product::count())->toBe(0);
});

it('catalog:seed-demo runs in production with --force', function () {
    config(['app.env' => 'production']);

    $this->artisan('catalog:seed-demo', ['--force' => true])->assertSuccessful();

    expect(Product::count())->toBe(4);
});
