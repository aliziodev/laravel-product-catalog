<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Database\Factories;

use Aliziodev\ProductCatalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'name' => ucwords($name),
            'slug' => str($name)->slug()->toString(),
            'description' => fake()->optional()->sentence(),
            'position' => fake()->numberBetween(0, 10),
        ];
    }

    public function withParent(Category $parent): static
    {
        return $this->state(['parent_id' => $parent->getKey()]);
    }
}
