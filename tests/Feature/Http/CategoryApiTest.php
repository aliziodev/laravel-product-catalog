<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Models\Category;

it('returns root categories with children', function () {
    $electronics = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics', 'position' => 1]);
    Category::factory()->create(['parent_id' => $electronics->id, 'name' => 'Smartphones', 'slug' => 'smartphones', 'position' => 1]);
    Category::factory()->create(['parent_id' => $electronics->id, 'name' => 'Laptops', 'slug' => 'laptops', 'position' => 2]);

    $response = $this->getJson('/catalog/categories')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    expect($response->json('data.0.slug'))->toBe('electronics')
        ->and($response->json('data.0.children'))->toHaveCount(2);
});

it('does not return child categories at the root level', function () {
    $parent = Category::factory()->create(['slug' => 'parent', 'position' => 1]);
    Category::factory()->create(['parent_id' => $parent->id, 'slug' => 'child', 'position' => 1]);

    $this->getJson('/catalog/categories')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('category response contains expected fields', function () {
    Category::factory()->create(['name' => 'Apparel', 'slug' => 'apparel', 'position' => 1]);

    $this->getJson('/catalog/categories')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'slug', 'parent_id', 'position', 'children']],
        ]);
});

it('root categories are ordered by position', function () {
    Category::factory()->create(['slug' => 'beta', 'position' => 2]);
    Category::factory()->create(['slug' => 'alpha', 'position' => 1]);

    $this->getJson('/catalog/categories')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'alpha')
        ->assertJsonPath('data.1.slug', 'beta');
});
