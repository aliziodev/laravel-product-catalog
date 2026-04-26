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
            'reserved_before' => null,
            'reserved_after' => null,
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

    public function reserve(int $qty = 5): static
    {
        $reservedBefore = fake()->numberBetween(0, 20);

        return $this->state(fn (array $attr) => [
            'type' => MovementType::Reserve,
            'delta' => $qty,
            'quantity_after' => $attr['quantity_before'],  // total qty unchanged
            'reserved_before' => $reservedBefore,
            'reserved_after' => $reservedBefore + $qty,
        ]);
    }

    public function release(int $qty = 3): static
    {
        $reservedBefore = fake()->numberBetween($qty, 30);

        return $this->state(fn (array $attr) => [
            'type' => MovementType::Release,
            'delta' => -$qty,
            'quantity_after' => $attr['quantity_before'],  // total qty unchanged
            'reserved_before' => $reservedBefore,
            'reserved_after' => $reservedBefore - $qty,
        ]);
    }

    public function withReason(string $reason): static
    {
        return $this->state(['reason' => $reason]);
    }
}
