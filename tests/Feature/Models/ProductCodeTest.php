<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductOptionValue;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Database\QueryException;

it('code is nullable by default', function () {
    $product = Product::factory()->create();

    expect($product->code)->toBeNull();
});

it('code can be set manually', function () {
    $product = Product::factory()->create(['code' => 'ADIDAS-UB22']);

    expect($product->code)->toBe('ADIDAS-UB22');
});

it('code is unique across products', function () {
    Product::factory()->create(['code' => 'SAME-CODE']);

    expect(fn () => Product::factory()->create(['code' => 'SAME-CODE']))
        ->toThrow(QueryException::class);
});

it('code allows multiple nulls (soft-delete aware)', function () {
    Product::factory()->count(3)->create(['code' => null]);

    expect(Product::whereNull('code')->count())->toBe(3);
});

it('withProductCode factory state sets a product code', function () {
    $product = Product::factory()->withProductCode('MY-CODE')->create();

    expect($product->code)->toBe('MY-CODE');
});

it('withProductCode factory state auto-generates a code when none given', function () {
    $product = Product::factory()->withProductCode()->create();

    expect($product->code)->not->toBeNull();
});

// --- buildVariantSku ---

it('buildVariantSku uses code as base', function () {
    $product = Product::factory()->create(['code' => 'ADIDAS-UB22']);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $variant->setRelation('optionValues', collect());

    expect($product->buildVariantSku($variant))->toBe('ADIDAS-UB22');
});

it('buildVariantSku falls back to slugified name when code is null', function () {
    $product = Product::factory()->create(['name' => 'Kaos Premium', 'code' => null]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $variant->setRelation('optionValues', collect());

    expect($product->buildVariantSku($variant))->toBe('KAOS-PREMIUM');
});

it('buildVariantSku appends option values to code', function () {
    $product = Product::factory()->create(['code' => 'ADIDAS-UB22']);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    $red = ProductOptionValue::factory()->create(['value' => 'Red']);
    $xl = ProductOptionValue::factory()->create(['value' => 'XL']);
    $variant->setRelation('optionValues', collect([$red, $xl]));

    expect($product->buildVariantSku($variant))->toBe('ADIDAS-UB22-RED-XL');
});

it('buildVariantSku uppercases option values', function () {
    $product = Product::factory()->create(['code' => 'TEE-01']);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    $value = ProductOptionValue::factory()->create(['value' => 'navy blue']);
    $variant->setRelation('optionValues', collect([$value]));

    expect($product->buildVariantSku($variant))->toBe('TEE-01-NAVY-BLUE');
});

it('buildVariantSku result can be applied to variant sku', function () {
    $product = Product::factory()->create(['code' => 'SHIRT-01']);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => null]);

    $value = ProductOptionValue::factory()->create(['value' => 'Blue']);
    $variant->setRelation('optionValues', collect([$value]));

    $suggested = $product->buildVariantSku($variant);
    $variant->update(['sku' => $suggested]);

    expect($variant->fresh()->sku)->toBe('SHIRT-01-BLUE');
});
