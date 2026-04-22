<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductOption;
use Aliziodev\ProductCatalog\Models\ProductOptionValue;
use Aliziodev\ProductCatalog\Models\ProductVariant;

it('creates a variant with a price', function () {
    $variant = ProductVariant::factory()->create(['price' => '29.99']);

    expect((float) $variant->price)->toBe(29.99);
});

it('detects when a variant is on sale', function () {
    $onSale = ProductVariant::factory()->onSale(39.99)->create(['price' => 29.99]);
    $notOnSale = ProductVariant::factory()->create(['price' => 29.99, 'compare_price' => null]);

    expect($onSale->isOnSale())->toBeTrue()
        ->and($notOnSale->isOnSale())->toBeFalse();
});

it('calculates discount percentage', function () {
    $variant = ProductVariant::factory()->create([
        'price' => 75.00,
        'compare_price' => 100.00,
    ]);

    expect($variant->discountPercentage())->toBe(25);
});

it('returns null discount percentage when not on sale', function () {
    $variant = ProductVariant::factory()->create(['compare_price' => null]);

    expect($variant->discountPercentage())->toBeNull();
});

it('builds display name from option values', function () {
    $product = Product::factory()->variable()->create();

    $colorOption = ProductOption::factory()->create(['product_id' => $product->id, 'name' => 'Color']);
    $sizeOption = ProductOption::factory()->create(['product_id' => $product->id, 'name' => 'Size']);

    $red = ProductOptionValue::factory()->create(['option_id' => $colorOption->id, 'value' => 'Red']);
    $xl = ProductOptionValue::factory()->create(['option_id' => $sizeOption->id, 'value' => 'XL']);

    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $variant->optionValues()->attach([$red->id, $xl->id]);

    expect($variant->displayName())->toBe('Red / XL');
});

it('scopes active variants', function () {
    $product = Product::factory()->create();
    ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => true]);
    ProductVariant::factory()->inactive()->create(['product_id' => $product->id]);

    expect($product->variants()->active()->count())->toBe(1);
});
