<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Drivers\NullInventoryProvider;
use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Aliziodev\ProductCatalog\Enums\MovementType;
use Aliziodev\ProductCatalog\Events\InventoryAdjusted;
use Aliziodev\ProductCatalog\Events\InventoryReserved;
use Aliziodev\ProductCatalog\Exceptions\InventoryException;
use Aliziodev\ProductCatalog\Facades\ProductCatalog;
use Aliziodev\ProductCatalog\Models\InventoryItem;
use Aliziodev\ProductCatalog\Models\InventoryMovement;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Support\Facades\Event;

// ---------------------------------------------------------------------------
// reserve()
// ---------------------------------------------------------------------------

it('reserve() increments reserved_quantity', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'reserved_quantity' => 2, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->reserve($variant, 3);

    expect(InventoryItem::where('variant_id', $variant->id)->value('reserved_quantity'))->toBe(5);
});

it('reserve() does not change total quantity', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->reserve($variant, 4);

    expect(InventoryItem::where('variant_id', $variant->id)->value('quantity'))->toBe(10);
});

it('reserve() throws when available stock is insufficient', function () {
    $variant = ProductVariant::factory()->create();
    // quantity=5, reserved=3 → available=2
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 5, 'reserved_quantity' => 3, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->reserve($variant, 3);
})->throws(InventoryException::class);

it('reserve() is a no-op for Allow policy', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 0, 'policy' => InventoryPolicy::Allow]);

    ProductCatalog::inventory()->reserve($variant, 5);

    expect(InventoryMovement::count())->toBe(0);
});

it('reserve() fires InventoryReserved event', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryReserved::class]);

    ProductCatalog::inventory()->reserve($variant, 4, 'order_placed');

    Event::assertDispatched(InventoryReserved::class, function ($e) {
        return $e->type === MovementType::Reserve
            && $e->quantity === 4
            && $e->reservedBefore === 0
            && $e->reservedAfter === 4
            && $e->reason === 'order_placed';
    });
});

// ---------------------------------------------------------------------------
// release()
// ---------------------------------------------------------------------------

it('release() decrements reserved_quantity', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'reserved_quantity' => 6, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->release($variant, 4);

    expect(InventoryItem::where('variant_id', $variant->id)->value('reserved_quantity'))->toBe(2);
});

it('release() does not change total quantity', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'reserved_quantity' => 5, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->release($variant, 3);

    expect(InventoryItem::where('variant_id', $variant->id)->value('quantity'))->toBe(10);
});

it('release() does not go below zero reserved', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'reserved_quantity' => 2, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->release($variant, 999);

    expect(InventoryItem::where('variant_id', $variant->id)->value('reserved_quantity'))->toBe(0);
});

it('release() is a no-op for Allow policy', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 0, 'policy' => InventoryPolicy::Allow]);

    ProductCatalog::inventory()->release($variant, 5);

    expect(InventoryMovement::count())->toBe(0);
});

it('release() fires InventoryReserved event', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'reserved_quantity' => 5, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryReserved::class]);

    ProductCatalog::inventory()->release($variant, 3, 'order_cancelled');

    Event::assertDispatched(InventoryReserved::class, function ($e) {
        return $e->type === MovementType::Release
            && $e->quantity === -3
            && $e->reservedBefore === 5
            && $e->reservedAfter === 2
            && $e->reason === 'order_cancelled';
    });
});

// ---------------------------------------------------------------------------
// commit()
// ---------------------------------------------------------------------------

it('commit() decrements both quantity and reserved_quantity', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'reserved_quantity' => 5, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->commit($variant, 3);

    $item = InventoryItem::where('variant_id', $variant->id)->first();
    expect($item->quantity)->toBe(7)
        ->and($item->reserved_quantity)->toBe(2);
});

it('commit() throws when reserved_quantity is insufficient', function () {
    $variant = ProductVariant::factory()->create();
    // only 2 reserved, trying to commit 5
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'reserved_quantity' => 2, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->commit($variant, 5);
})->throws(InventoryException::class);

it('commit() throws instead of writing a negative quantity when stock was set below the reservation', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'reserved_quantity' => 8, 'policy' => InventoryPolicy::Track]);

    // A stock count finds only 5 units while 8 are still reserved.
    ProductCatalog::inventory()->set($variant, 5);

    expect(fn () => ProductCatalog::inventory()->commit($variant, 8))
        ->toThrow(InventoryException::class, 'Insufficient stock');

    $item = InventoryItem::where('variant_id', $variant->id)->first();
    expect($item->quantity)->toBe(5)
        ->and($item->reserved_quantity)->toBe(8)
        ->and(InventoryMovement::where('type', MovementType::Deduction)->count())->toBe(0);
});

it('commit() is a no-op for Allow policy', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 0, 'policy' => InventoryPolicy::Allow]);

    ProductCatalog::inventory()->commit($variant, 5);

    expect(InventoryMovement::count())->toBe(0);
});

it('commit() fires InventoryAdjusted event', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'reserved_quantity' => 5, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryAdjusted::class]);

    ProductCatalog::inventory()->commit($variant, 3, 'order_fulfilled');

    Event::assertDispatched(InventoryAdjusted::class, fn ($e) => $e->previousQuantity === 10 && $e->newQuantity === 7);
});

// ---------------------------------------------------------------------------
// Movement records — reserve
// ---------------------------------------------------------------------------

it('reserve() records a movement with type Reserve', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 20, 'reserved_quantity' => 0, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->reserve($variant, 5, 'order_placed');

    $movement = InventoryMovement::where('variant_id', $variant->id)->first();

    expect($movement->type)->toBe(MovementType::Reserve)
        ->and($movement->delta)->toBe(5)
        ->and($movement->quantity_before)->toBe(20)
        ->and($movement->quantity_after)->toBe(20)  // total qty unchanged
        ->and($movement->reserved_before)->toBe(0)
        ->and($movement->reserved_after)->toBe(5)
        ->and($movement->reason)->toBe('order_placed');
});

it('release() records a movement with type Release', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 20, 'reserved_quantity' => 8, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->release($variant, 3, 'order_cancelled');

    $movement = InventoryMovement::where('variant_id', $variant->id)->first();

    expect($movement->type)->toBe(MovementType::Release)
        ->and($movement->delta)->toBe(-3)
        ->and($movement->quantity_before)->toBe(20)
        ->and($movement->quantity_after)->toBe(20)  // total qty unchanged
        ->and($movement->reserved_before)->toBe(8)
        ->and($movement->reserved_after)->toBe(5)
        ->and($movement->reason)->toBe('order_cancelled');
});

it('commit() records a Deduction movement with both quantity and reserved changes', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'reserved_quantity' => 5, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->commit($variant, 3, 'order_fulfilled');

    $movement = InventoryMovement::where('variant_id', $variant->id)->first();

    expect($movement->type)->toBe(MovementType::Deduction)
        ->and($movement->delta)->toBe(-3)
        ->and($movement->quantity_before)->toBe(10)
        ->and($movement->quantity_after)->toBe(7)
        ->and($movement->reserved_before)->toBe(5)
        ->and($movement->reserved_after)->toBe(2)
        ->and($movement->reason)->toBe('order_fulfilled');
});

it('regular adjust() movements have null reserved columns', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->adjust($variant, -3, 'sale');

    $movement = InventoryMovement::where('variant_id', $variant->id)->first();

    expect($movement->reserved_before)->toBeNull()
        ->and($movement->reserved_after)->toBeNull();
});

// ---------------------------------------------------------------------------
// Movement model helpers
// ---------------------------------------------------------------------------

it('movement isReservationMovement returns true for Reserve type', function () {
    $movement = InventoryMovement::factory()->reserve(5)->create();

    expect($movement->isReservationMovement())->toBeTrue()
        ->and($movement->affectsReservation())->toBeTrue();
});

it('movement isReservationMovement returns true for Release type', function () {
    $movement = InventoryMovement::factory()->release(3)->create();

    expect($movement->isReservationMovement())->toBeTrue();
});

it('movement isReservationMovement returns false for stock movements', function () {
    $movement = InventoryMovement::factory()->restock(10)->create();

    expect($movement->isReservationMovement())->toBeFalse()
        ->and($movement->affectsReservation())->toBeFalse();
});

// ---------------------------------------------------------------------------
// InventoryReserved event helpers
// ---------------------------------------------------------------------------

it('InventoryReserved isReserve returns true for Reserve type', function () {
    $variant = ProductVariant::factory()->create();
    $event = new InventoryReserved($variant, MovementType::Reserve, 5, 0, 5);

    expect($event->isReserve())->toBeTrue()
        ->and($event->isRelease())->toBeFalse();
});

it('InventoryReserved isRelease returns true for Release type', function () {
    $variant = ProductVariant::factory()->create();
    $event = new InventoryReserved($variant, MovementType::Release, -3, 5, 2);

    expect($event->isRelease())->toBeTrue()
        ->and($event->isReserve())->toBeFalse();
});

// ---------------------------------------------------------------------------
// NullInventoryProvider — no-ops
// ---------------------------------------------------------------------------

it('null provider reserve/release/commit do not throw', function () {
    $variant = ProductVariant::factory()->create();
    $provider = new NullInventoryProvider;

    $provider->reserve($variant, 5);
    $provider->release($variant, 3);
    $provider->commit($variant, 2);

    expect(InventoryMovement::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Full lifecycle: reserve → release / reserve → commit
// ---------------------------------------------------------------------------

it('available quantity reflects reserved stock throughout lifecycle', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'policy' => InventoryPolicy::Track]);
    $inventory = ProductCatalog::inventory();

    expect($inventory->getQuantity($variant))->toBe(10);

    $inventory->reserve($variant, 3);
    expect($inventory->getQuantity($variant))->toBe(7);

    $inventory->release($variant, 1);
    expect($inventory->getQuantity($variant))->toBe(8);

    $inventory->commit($variant, 2);  // commits remaining 2 of the 2 reserved
    expect($inventory->getQuantity($variant))->toBe(8);

    $item = InventoryItem::where('variant_id', $variant->id)->first();
    expect($item->quantity)->toBe(8)
        ->and($item->reserved_quantity)->toBe(0);
});

it('movements provide complete audit trail for reserve → commit lifecycle', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 20, 'policy' => InventoryPolicy::Track]);
    $inventory = ProductCatalog::inventory();

    $inventory->reserve($variant, 5, 'order_placed');
    $inventory->commit($variant, 5, 'order_fulfilled');

    $movements = InventoryMovement::where('variant_id', $variant->id)->orderBy('id')->get();

    expect($movements)->toHaveCount(2)
        ->and($movements[0]->type)->toBe(MovementType::Reserve)
        ->and($movements[1]->type)->toBe(MovementType::Deduction)
        ->and($movements[1]->reserved_before)->toBe(5)
        ->and($movements[1]->reserved_after)->toBe(0);
});

// ---------------------------------------------------------------------------
// Quantity validation
// ---------------------------------------------------------------------------

it('rejects zero and negative quantities', function (string $method, int $quantity) {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'reserved_quantity' => 8, 'policy' => InventoryPolicy::Track]);

    expect(fn () => ProductCatalog::inventory()->{$method}($variant, $quantity))
        ->toThrow(InventoryException::class, 'Quantity must be a positive integer');

    $item = InventoryItem::where('variant_id', $variant->id)->first();
    expect($item->quantity)->toBe(10)
        ->and($item->reserved_quantity)->toBe(8)
        ->and(InventoryMovement::count())->toBe(0);
})->with([
    'reserve zero' => ['reserve', 0],
    'reserve negative' => ['reserve', -5],
    'release zero' => ['release', 0],
    'release negative' => ['release', -5],
    'commit zero' => ['commit', 0],
    'commit negative' => ['commit', -5],
]);

it('rejects a negative quantity even when the policy would make the call a no-op', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 0, 'policy' => InventoryPolicy::Allow]);

    ProductCatalog::inventory()->reserve($variant, -1);
})->throws(InventoryException::class, 'Quantity must be a positive integer');

// ---------------------------------------------------------------------------
// Deny policy
// ---------------------------------------------------------------------------

it('reserve() throws for Deny policy and leaves the item untouched', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'policy' => InventoryPolicy::Deny]);

    Event::fake([InventoryReserved::class]);

    expect(fn () => ProductCatalog::inventory()->reserve($variant, 1))
        ->toThrow(InventoryException::class, 'purchase policy set to deny');

    $item = InventoryItem::where('variant_id', $variant->id)->first();
    expect($item->reserved_quantity)->toBe(0)
        ->and(InventoryMovement::count())->toBe(0);

    Event::assertNotDispatched(InventoryReserved::class);
});

// ---------------------------------------------------------------------------
// adjust() and reserved stock
// ---------------------------------------------------------------------------

it('adjust() refuses to deduct units held by reservations', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 5, 'reserved_quantity' => 3, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryAdjusted::class]);

    expect(fn () => ProductCatalog::inventory()->adjust($variant, -3, 'sale'))
        ->toThrow(InventoryException::class, 'requested 3, available 2');

    $item = InventoryItem::where('variant_id', $variant->id)->first();
    expect($item->quantity)->toBe(5)
        ->and($item->reserved_quantity)->toBe(3)
        ->and(InventoryMovement::count())->toBe(0);

    Event::assertNotDispatched(InventoryAdjusted::class);
});

it('adjust() can deduct every available unit while reservations stay covered', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 5, 'reserved_quantity' => 3, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->adjust($variant, -2, 'damage');

    $item = InventoryItem::where('variant_id', $variant->id)->first();
    expect($item->quantity)->toBe(3)
        ->and($item->reserved_quantity)->toBe(3);

    ProductCatalog::inventory()->commit($variant, 3);

    expect($item->fresh()->quantity)->toBe(0);
});

it('adjust() still restocks when quantity is already below the reservation', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 1, 'reserved_quantity' => 4, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->adjust($variant, 2, 'purchase');

    expect(InventoryItem::where('variant_id', $variant->id)->value('quantity'))->toBe(3);
});

it('set() can still bring quantity below the reservation for stock counts', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 5, 'reserved_quantity' => 3, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->set($variant, 1, 'stocktake');

    $item = InventoryItem::where('variant_id', $variant->id)->first();
    expect($item->quantity)->toBe(1)
        ->and($item->reserved_quantity)->toBe(3);
});
