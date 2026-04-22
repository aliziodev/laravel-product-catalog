<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Models\Category;
use Aliziodev\ProductCatalog\Models\Product;

// --- parent() relationship ---

it('category parent returns null for root category', function () {
    $root = Category::factory()->create(['parent_id' => null]);

    expect($root->parent)->toBeNull();
});

it('category parent returns the parent category', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    expect($child->parent->id)->toBe($parent->id);
});

// --- products() relationship ---

it('category products returns attached products', function () {
    $category = Category::factory()->create();
    $products = Product::factory()->count(3)->create();

    $products->each(fn ($p) => $p->categories()->attach($category));

    expect($category->products()->count())->toBe(3);
});

it('category products returns empty when no products attached', function () {
    $category = Category::factory()->create();

    expect($category->products()->count())->toBe(0);
});

// --- isRoot() ---

it('isRoot returns true when parent_id is null', function () {
    $category = Category::factory()->create(['parent_id' => null]);

    expect($category->isRoot())->toBeTrue();
});

it('isRoot returns false when category has a parent', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    expect($child->isRoot())->toBeFalse();
});
