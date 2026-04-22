<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Database\Factories;

use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Aliziodev\ProductCatalog\Models\InventoryItem;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'variant_id' => ProductVariant::factory(),
            'quantity' => fake()->numberBetween(0, 100),
            'reserved_quantity' => 0,
            'low_stock_threshold' => null,
            'policy' => InventoryPolicy::Track,
        ];
    }

    public function allow(): static
    {
        return $this->state(['policy' => InventoryPolicy::Allow]);
    }

    public function deny(): static
    {
        return $this->state(['policy' => InventoryPolicy::Deny]);
    }
}
