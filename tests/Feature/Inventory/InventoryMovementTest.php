<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Aliziodev\ProductCatalog\Enums\MovementType;
use Aliziodev\ProductCatalog\Facades\ProductCatalog;
use Aliziodev\ProductCatalog\Models\InventoryItem;
use Aliziodev\ProductCatalog\Models\InventoryMovement;
use Aliziodev\ProductCatalog\Models\ProductVariant;

it('records a movement when stock is adjusted', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->adjust($variant, -3, 'sale');

    $movement = InventoryMovement::where('variant_id', $variant->id)->first();

    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe(MovementType::Deduction)
        ->and($movement->delta)->toBe(-3)
        ->and($movement->quantity_before)->toBe(10)
        ->and($movement->quantity_after)->toBe(7)
        ->and($movement->reason)->toBe('sale');
});

it('records a movement when stock is restocked', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 5, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->adjust($variant, 10, 'restock');

    $movement = InventoryMovement::where('variant_id', $variant->id)->first();

    expect($movement->type)->toBe(MovementType::Restock)
        ->and($movement->delta)->toBe(10)
        ->and($movement->quantity_before)->toBe(5)
        ->and($movement->quantity_after)->toBe(15);
});

it('records a movement when stock is set absolutely', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 5, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->set($variant, 20, 'manual count');

    $movement = InventoryMovement::where('variant_id', $variant->id)->first();

    expect($movement->type)->toBe(MovementType::Set)
        ->and($movement->delta)->toBe(15)
        ->and($movement->quantity_before)->toBe(5)
        ->and($movement->quantity_after)->toBe(20)
        ->and($movement->reason)->toBe('manual count');
});

it('stores null reason when no reason is provided', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 5, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->adjust($variant, 5);

    expect(InventoryMovement::where('variant_id', $variant->id)->value('reason'))->toBeNull();
});

it('does not record a movement for non-tracked policy', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 0, 'policy' => InventoryPolicy::Allow]);

    ProductCatalog::inventory()->adjust($variant, 10);

    expect(InventoryMovement::count())->toBe(0);
});

it('movement has no updated_at column', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 5, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->adjust($variant, 1);

    $movement = InventoryMovement::first();

    expect($movement->updated_at)->toBeNull();
});

it('movement isInbound returns true for positive delta', function () {
    $movement = InventoryMovement::factory()->restock(5)->create();

    expect($movement->isInbound())->toBeTrue()
        ->and($movement->isOutbound())->toBeFalse();
});

it('movement isOutbound returns true for negative delta', function () {
    $movement = InventoryMovement::factory()->deduction(3)->create();

    expect($movement->isOutbound())->toBeTrue()
        ->and($movement->isInbound())->toBeFalse();
});

it('variant has many movements', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 20, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->adjust($variant, -2, 'order 1');
    ProductCatalog::inventory()->adjust($variant, -3, 'order 2');
    ProductCatalog::inventory()->adjust($variant, 10, 'restock');

    expect($variant->movements()->count())->toBe(3);
});

it('inventory item movements relation returns correct records', function () {
    $variant = ProductVariant::factory()->create();
    $item = InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->adjust($variant, -5);
    ProductCatalog::inventory()->set($variant, 50);

    expect($item->movements()->count())->toBe(2);
});

it('stores polymorphic reference on movement', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'policy' => InventoryPolicy::Track]);

    $reference = ProductVariant::factory()->create();
    ProductCatalog::inventory()->adjust($variant, -2, 'test ref', $reference);

    $movement = InventoryMovement::where('variant_id', $variant->id)->first();

    expect($movement->referenceable_type)->toBe($reference->getMorphClass())
        ->and((string) $movement->referenceable_id)->toBe((string) $reference->getKey());
});
