<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductOption;
use Aliziodev\ProductCatalog\Models\ProductOptionValue;
use Aliziodev\ProductCatalog\Models\ProductVariant;

// --- ProductOption: product() ---

it('option belongs to a product', function () {
    $product = Product::factory()->create();
    $option = ProductOption::factory()->create(['product_id' => $product->id]);

    expect($option->product->id)->toBe($product->id);
});

// --- ProductOption: values() ---

it('option has many values ordered by position', function () {
    $product = Product::factory()->create();
    $option = ProductOption::factory()->create(['product_id' => $product->id]);

    ProductOptionValue::factory()->create(['option_id' => $option->id, 'value' => 'Red',  'position' => 2]);
    ProductOptionValue::factory()->create(['option_id' => $option->id, 'value' => 'Blue', 'position' => 1]);

    $values = $option->values;

    expect($values)->toHaveCount(2)
        ->and($values->first()->value)->toBe('Blue')
        ->and($values->last()->value)->toBe('Red');
});

it('option values returns empty when no values exist', function () {
    $product = Product::factory()->create();
    $option = ProductOption::factory()->create(['product_id' => $product->id]);

    expect($option->values)->toHaveCount(0);
});

// --- ProductOptionValue: option() ---

it('option value belongs to an option', function () {
    $product = Product::factory()->create();
    $option = ProductOption::factory()->create(['product_id' => $product->id]);
    $value = ProductOptionValue::factory()->create(['option_id' => $option->id]);

    expect($value->option->id)->toBe($option->id);
});

// --- ProductOptionValue: variants() ---

it('option value returns attached variants', function () {
    $product = Product::factory()->create();
    $option = ProductOption::factory()->create(['product_id' => $product->id]);
    $value = ProductOptionValue::factory()->create(['option_id' => $option->id]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    $variant->optionValues()->attach($value);

    expect($value->variants()->count())->toBe(1)
        ->and($value->variants->first()->id)->toBe($variant->id);
});

it('option value variants returns empty when none attached', function () {
    $product = Product::factory()->create();
    $option = ProductOption::factory()->create(['product_id' => $product->id]);
    $value = ProductOptionValue::factory()->create(['option_id' => $option->id]);

    expect($value->variants()->count())->toBe(0);
});
