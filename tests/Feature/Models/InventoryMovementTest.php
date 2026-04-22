<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Models\InventoryMovement;
use Aliziodev\ProductCatalog\Models\ProductVariant;

// --- variant() relationship ---

it('inventoryMovement belongs to a variant', function () {
    $variant = ProductVariant::factory()->create();
    $movement = InventoryMovement::factory()->create(['variant_id' => $variant->id]);

    expect($movement->variant->id)->toBe($variant->id);
});

// --- referenceable() ---

it('referenceable returns null when no reference is set', function () {
    $movement = InventoryMovement::factory()->create([
        'referenceable_type' => null,
        'referenceable_id' => null,
    ]);

    expect($movement->referenceable)->toBeNull();
});
