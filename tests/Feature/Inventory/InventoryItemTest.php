<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Exceptions\InventoryException;
use Aliziodev\ProductCatalog\Models\InventoryItem;

// --- reserved_quantity ---

it('reserved_quantity defaults to zero', function () {
    $item = InventoryItem::factory()->create();

    expect($item->reserved_quantity)->toBe(0);
});

it('availableQuantity returns quantity minus reserved', function () {
    $item = InventoryItem::factory()->create(['quantity' => 10, 'reserved_quantity' => 3]);

    expect($item->availableQuantity())->toBe(7);
});

it('availableQuantity never returns negative', function () {
    $item = InventoryItem::factory()->create(['quantity' => 2, 'reserved_quantity' => 10]);

    expect($item->availableQuantity())->toBe(0);
});

it('reserve increments reserved_quantity', function () {
    $item = InventoryItem::factory()->create(['quantity' => 20, 'reserved_quantity' => 0]);

    $item->reserve(5);

    expect($item->fresh()->reserved_quantity)->toBe(5);
});

it('release decrements reserved_quantity', function () {
    $item = InventoryItem::factory()->create(['quantity' => 20, 'reserved_quantity' => 8]);

    $item->release(3);

    expect($item->fresh()->reserved_quantity)->toBe(5);
});

it('release does not go below zero', function () {
    $item = InventoryItem::factory()->create(['quantity' => 20, 'reserved_quantity' => 2]);

    $item->release(999);

    expect($item->fresh()->reserved_quantity)->toBe(0);
});

it('rejects a negative quantity for reserve and release', function (string $method) {
    $item = InventoryItem::factory()->create(['quantity' => 20, 'reserved_quantity' => 8]);

    expect(fn () => $item->{$method}(-5))
        ->toThrow(InventoryException::class, 'Quantity must be a positive integer');

    expect($item->fresh()->reserved_quantity)->toBe(8);
})->with(['reserve', 'release']);

// --- low_stock_threshold ---

it('low_stock_threshold is null by default', function () {
    $item = InventoryItem::factory()->create();

    expect($item->low_stock_threshold)->toBeNull();
});

it('isLowStock returns false when threshold is null', function () {
    $item = InventoryItem::factory()->create(['quantity' => 2, 'low_stock_threshold' => null]);

    expect($item->isLowStock())->toBeFalse();
});

it('isLowStock returns true when available quantity is at or below threshold', function () {
    $item = InventoryItem::factory()->create([
        'quantity' => 5,
        'reserved_quantity' => 2,
        'low_stock_threshold' => 5,
    ]);

    // available = 5 - 2 = 3, threshold = 5 → low stock
    expect($item->isLowStock())->toBeTrue();
});

it('isLowStock returns false when available quantity is above threshold', function () {
    $item = InventoryItem::factory()->create([
        'quantity' => 20,
        'reserved_quantity' => 0,
        'low_stock_threshold' => 5,
    ]);

    expect($item->isLowStock())->toBeFalse();
});

it('scopeLowStock returns only items below threshold', function () {
    InventoryItem::factory()->create(['quantity' => 2, 'reserved_quantity' => 0, 'low_stock_threshold' => 5]);
    InventoryItem::factory()->create(['quantity' => 20, 'reserved_quantity' => 0, 'low_stock_threshold' => 5]);
    InventoryItem::factory()->create(['quantity' => 5, 'reserved_quantity' => 0, 'low_stock_threshold' => null]);

    expect(InventoryItem::lowStock()->count())->toBe(1);
});

it('scopeLowStock accounts for reserved_quantity', function () {
    // quantity=10, reserved=8 → available=2, threshold=5 → low stock
    InventoryItem::factory()->create(['quantity' => 10, 'reserved_quantity' => 8, 'low_stock_threshold' => 5]);
    // quantity=10, reserved=0 → available=10, threshold=5 → ok
    InventoryItem::factory()->create(['quantity' => 10, 'reserved_quantity' => 0, 'low_stock_threshold' => 5]);

    expect(InventoryItem::lowStock()->count())->toBe(1);
});

it('scopeLowStock includes items whose reservations exceed quantity', function () {
    // quantity=5, reserved=8 → available=0, threshold=2 → low stock.
    // MySQL rejected the old `quantity - reserved_quantity` expression here (error 1690).
    InventoryItem::factory()->create(['quantity' => 5, 'reserved_quantity' => 8, 'low_stock_threshold' => 2]);
    InventoryItem::factory()->create(['quantity' => 10, 'reserved_quantity' => 0, 'low_stock_threshold' => 2]);

    expect(InventoryItem::lowStock()->count())->toBe(1);
});
