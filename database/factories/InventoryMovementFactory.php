<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Database\Factories;

use Aliziodev\ProductCatalog\Enums\MovementType;
use Aliziodev\ProductCatalog\Models\InventoryMovement;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        $before = fake()->numberBetween(0, 100);
        $delta = fake()->numberBetween(1, 20);

        return [
            'variant_id' => ProductVariant::factory(),
            'type' => MovementType::Restock,
            'delta' => $delta,
            'quantity_before' => $before,
            'quantity_after' => $before + $delta,
            'reason' => null,
        ];
    }

    public function restock(int $delta = 10): static
    {
        return $this->state(fn (array $attr) => [
            'type' => MovementType::Restock,
            'delta' => $delta,
            'quantity_after' => $attr['quantity_before'] + $delta,
        ]);
    }

    public function deduction(int $delta = 3): static
    {
        return $this->state(fn (array $attr) => [
            'type' => MovementType::Deduction,
            'delta' => -$delta,
            'quantity_before' => max($delta, $attr['quantity_before']),
            'quantity_after' => max($delta, $attr['quantity_before']) - $delta,
        ]);
    }

    public function withReason(string $reason): static
    {
        return $this->state(['reason' => $reason]);
    }
}
