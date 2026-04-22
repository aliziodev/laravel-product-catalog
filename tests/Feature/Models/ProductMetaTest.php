<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;

// --- Product meta ---

it('product meta is null by default', function () {
    $product = Product::factory()->create();

    expect($product->meta)->toBeNull();
});

it('product meta can store arbitrary key-value pairs', function () {
    $product = Product::factory()->create([
        'meta' => ['material' => 'cotton', 'origin' => 'Indonesia', 'care' => ['machine wash', 'no bleach']],
    ]);

    expect($product->meta['material'])->toBe('cotton')
        ->and($product->meta['origin'])->toBe('Indonesia')
        ->and($product->meta['care'])->toBe(['machine wash', 'no bleach']);
});

it('product meta is cast as array', function () {
    $product = Product::factory()->create(['meta' => ['key' => 'value']]);

    expect($product->fresh()->meta)->toBeArray();
});

it('product meta can be updated', function () {
    $product = Product::factory()->create(['meta' => ['color' => 'red']]);

    $product->update(['meta' => ['color' => 'blue', 'size' => 'XL']]);

    expect($product->fresh()->meta['color'])->toBe('blue')
        ->and($product->fresh()->meta['size'])->toBe('XL');
});

// --- Variant meta ---

it('variant meta is null by default', function () {
    $variant = ProductVariant::factory()->create();

    expect($variant->meta)->toBeNull();
});

it('variant meta can store arbitrary key-value pairs', function () {
    $variant = ProductVariant::factory()->create([
        'meta' => ['barcode' => '8991234567890', 'customs_code' => 'HS-6109'],
    ]);

    expect($variant->meta['barcode'])->toBe('8991234567890')
        ->and($variant->meta['customs_code'])->toBe('HS-6109');
});

// --- Variant dimensions ---

it('variant dimensions are null by default', function () {
    $variant = ProductVariant::factory()->create();

    expect($variant->length)->toBeNull()
        ->and($variant->width)->toBeNull()
        ->and($variant->height)->toBeNull();
});

it('variant dimensions can be set', function () {
    $variant = ProductVariant::factory()->create([
        'length' => 30.00,
        'width' => 20.00,
        'height' => 5.50,
    ]);

    expect((float) $variant->length)->toBe(30.0)
        ->and((float) $variant->width)->toBe(20.0)
        ->and((float) $variant->height)->toBe(5.5);
});
