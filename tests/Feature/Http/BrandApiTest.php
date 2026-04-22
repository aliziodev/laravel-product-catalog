<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Models\Brand;

it('returns an empty list when no brands exist', function () {
    $this->getJson('/catalog/brands')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns all brands ordered by name', function () {
    Brand::factory()->create(['name' => 'ZenBrand', 'slug' => 'zenbrand']);
    Brand::factory()->create(['name' => 'AcmeBrand', 'slug' => 'acmebrand']);

    $this->getJson('/catalog/brands')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.slug', 'acmebrand')
        ->assertJsonPath('data.1.slug', 'zenbrand');
});

it('brand response contains expected fields', function () {
    Brand::factory()->create([
        'name' => 'TechCo',
        'slug' => 'techco',
        'description' => 'Premium tech',
        'website_url' => 'https://techco.example.com',
    ]);

    $this->getJson('/catalog/brands')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'slug', 'description', 'logo_url', 'website_url']],
        ]);
});
