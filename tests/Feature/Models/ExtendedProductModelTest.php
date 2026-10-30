<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Models\Brand;
use Aliziodev\ProductCatalog\Models\Category;
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductOption;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Aliziodev\ProductCatalog\Models\Tag;

/**
 * An application model whose class name is not "Product", so Laravel's
 * naming conventions would derive a catalog_product_id foreign key.
 */
class CatalogProduct extends Product {}

beforeEach(function () {
    config(['product-catalog.model' => CatalogProduct::class]);
});

it('loads variants, the default variant and options on an extended product model', function () {
    $product = Product::factory()->create();
    $default = ProductVariant::factory()->create(['product_id' => $product->id, 'is_default' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'is_default' => false]);
    ProductOption::factory()->create(['product_id' => $product->id]);

    $extended = CatalogProduct::with(['variants', 'defaultVariant', 'options'])->findOrFail($product->id);

    expect($extended->variants)->toHaveCount(2)
        ->and($extended->defaultVariant->id)->toBe($default->id)
        ->and($extended->options)->toHaveCount(1);
});

it('returns the configured product model from every inverse relation', function () {
    $brand = Brand::factory()->create();
    $category = Category::factory()->create();
    $tag = Tag::factory()->create();

    $product = Product::factory()->create(['brand_id' => $brand->id]);
    $product->categories()->attach($category);
    $product->tags()->attach($tag);

    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $option = ProductOption::factory()->create(['product_id' => $product->id]);

    expect($variant->product)->toBeInstanceOf(CatalogProduct::class)
        ->and($option->product)->toBeInstanceOf(CatalogProduct::class)
        ->and($brand->products->sole())->toBeInstanceOf(CatalogProduct::class)
        ->and($category->products->sole())->toBeInstanceOf(CatalogProduct::class)
        ->and($tag->products->sole())->toBeInstanceOf(CatalogProduct::class);
});
