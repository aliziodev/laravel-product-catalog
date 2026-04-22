<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Database\Factories;

use Aliziodev\ProductCatalog\Enums\ProductStatus;
use Aliziodev\ProductCatalog\Enums\ProductType;
use Aliziodev\ProductCatalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => ucwords($name),
            'description' => fake()->paragraphs(2, true),
            'short_description' => fake()->optional()->sentence(),
            'type' => ProductType::Simple,
            'status' => ProductStatus::Draft,
            'featured_image_path' => fake()->optional()->imageUrl(),
            'meta_title' => null,
            'meta_description' => null,
            'published_at' => null,
        ];
    }

    public function withProductCode(?string $code = null): static
    {
        return $this->state([
            'code' => $code ?? strtoupper(fake()->bothify('??-####')),
        ]);
    }

    public function published(): static
    {
        return $this->state([
            'status' => ProductStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(['status' => ProductStatus::Draft]);
    }

    public function archived(): static
    {
        return $this->state(['status' => ProductStatus::Archived]);
    }

    public function variable(): static
    {
        return $this->state(['type' => ProductType::Variable]);
    }
}
