<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Database\Factories;

use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => fake()->unique()->bothify('SKU-####??'),
            'price' => fake()->randomFloat(2, 1, 999),
            'compare_price' => null,
            'cost_price' => null,
            'weight' => fake()->optional()->randomFloat(3, 0.1, 50),
            'is_default' => false,
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function onSale(?float $comparePrice = null): static
    {
        return $this->state(function (array $attributes) use ($comparePrice) {
            $basePrice = (float) $attributes['price'];

            return [
                'compare_price' => $comparePrice ?? round($basePrice * 1.3, 2),
            ];
        });
    }
}
