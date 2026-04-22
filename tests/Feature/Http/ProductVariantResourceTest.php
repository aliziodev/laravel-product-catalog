<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Http\Resources\ProductVariantResource;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Http\Request;

// --- all fields present ---

it('resource contains all expected keys', function () {
    $variant = ProductVariant::factory()->create();

    $data = ProductVariantResource::make($variant)->toArray(new Request);

    expect($data)->toHaveKeys([
        'id', 'sku', 'price', 'compare_price',
        'is_default', 'is_active', 'is_on_sale', 'discount_percentage',
        'weight', 'length', 'width', 'height',
        'position', 'meta',
    ]);
});

// --- price is cast to float ---

it('price is returned as float', function () {
    $variant = ProductVariant::factory()->create(['price' => 150000]);

    $data = ProductVariantResource::make($variant)->toArray(new Request);

    expect($data['price'])->toBe(150000.0)
        ->and($data['price'])->toBeFloat();
});

// --- compare_price null vs float ---

it('compare_price is null when not set', function () {
    $variant = ProductVariant::factory()->create(['compare_price' => null]);

    $data = ProductVariantResource::make($variant)->toArray(new Request);

    expect($data['compare_price'])->toBeNull();
});

it('compare_price is returned as float when set', function () {
    $variant = ProductVariant::factory()->create(['price' => 80000, 'compare_price' => 100000]);

    $data = ProductVariantResource::make($variant)->toArray(new Request);

    expect($data['compare_price'])->toBe(100000.0)
        ->and($data['compare_price'])->toBeFloat();
});

// --- is_on_sale and discount_percentage ---

it('is_on_sale is true and discount_percentage is set when on sale', function () {
    $variant = ProductVariant::factory()->create(['price' => 80000, 'compare_price' => 100000]);

    $data = ProductVariantResource::make($variant)->toArray(new Request);

    expect($data['is_on_sale'])->toBeTrue()
        ->and($data['discount_percentage'])->toBe(20);
});

it('is_on_sale is false and discount_percentage is null when not on sale', function () {
    $variant = ProductVariant::factory()->create(['price' => 100000, 'compare_price' => null]);

    $data = ProductVariantResource::make($variant)->toArray(new Request);

    expect($data['is_on_sale'])->toBeFalse()
        ->and($data['discount_percentage'])->toBeNull();
});

// --- dimensions null vs float ---

it('dimensions are null when not set', function () {
    $variant = ProductVariant::factory()->create([
        'weight' => null,
        'length' => null,
        'width' => null,
        'height' => null,
    ]);

    $data = ProductVariantResource::make($variant)->toArray(new Request);

    expect($data['weight'])->toBeNull()
        ->and($data['length'])->toBeNull()
        ->and($data['width'])->toBeNull()
        ->and($data['height'])->toBeNull();
});

it('dimensions are returned as float when set', function () {
    $variant = ProductVariant::factory()->create([
        'weight' => 0.5,
        'length' => 30.0,
        'width' => 20.0,
        'height' => 10.0,
    ]);

    $data = ProductVariantResource::make($variant)->toArray(new Request);

    expect($data['weight'])->toBe(0.5)
        ->and($data['length'])->toBe(30.0)
        ->and($data['width'])->toBe(20.0)
        ->and($data['height'])->toBe(10.0);
});

// --- meta ---

it('meta is null when not set', function () {
    $variant = ProductVariant::factory()->create(['meta' => null]);

    $data = ProductVariantResource::make($variant)->toArray(new Request);

    expect($data['meta'])->toBeNull();
});

it('meta is returned as array when set', function () {
    $variant = ProductVariant::factory()->create(['meta' => ['barcode' => '123456']]);

    $data = ProductVariantResource::make($variant)->toArray(new Request);

    expect($data['meta'])->toBe(['barcode' => '123456']);
});
