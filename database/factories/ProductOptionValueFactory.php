<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Database\Factories;

use Aliziodev\ProductCatalog\Models\ProductOption;
use Aliziodev\ProductCatalog\Models\ProductOptionValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductOptionValue>
 */
class ProductOptionValueFactory extends Factory
{
    protected $model = ProductOptionValue::class;

    public function definition(): array
    {
        return [
            'option_id' => ProductOption::factory(),
            'value' => fake()->word(),
            'position' => fake()->numberBetween(0, 10),
        ];
    }
}
