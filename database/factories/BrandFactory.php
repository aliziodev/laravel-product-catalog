<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Database\Factories;

use Aliziodev\ProductCatalog\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'description' => fake()->optional()->sentence(),
            'logo_url' => fake()->optional()->imageUrl(200, 200),
            'website_url' => fake()->optional()->url(),
        ];
    }
}
