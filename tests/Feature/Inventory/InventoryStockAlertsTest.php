<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Aliziodev\ProductCatalog\Events\InventoryAdjusted;
use Aliziodev\ProductCatalog\Events\InventoryLowStock;
use Aliziodev\ProductCatalog\Events\InventoryOutOfStock;
use Aliziodev\ProductCatalog\Events\InventoryReserved;
use Aliziodev\ProductCatalog\Facades\ProductCatalog;
use Aliziodev\ProductCatalog\Models\InventoryItem;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Support\Facades\Event;

// ---------------------------------------------------------------------------
// InventoryOutOfStock
// ---------------------------------------------------------------------------

it('fires InventoryOutOfStock when adjust drains stock to zero', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 5, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryOutOfStock::class]);

    ProductCatalog::inventory()->adjust($variant, -5);

    Event::assertDispatched(InventoryOutOfStock::class, fn ($e) => $e->variant->is($variant));
});

it('fires InventoryOutOfStock when adjust drops available to zero with reserved stock present', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'reserved_quantity' => 7, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryOutOfStock::class]);

    ProductCatalog::inventory()->adjust($variant, -3); // quantity becomes 7, available = 7 - 7 = 0

    Event::assertDispatched(InventoryOutOfStock::class);
});

it('fires InventoryOutOfStock when set drops stock to zero', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryOutOfStock::class]);

    ProductCatalog::inventory()->set($variant, 0);

    Event::assertDispatched(InventoryOutOfStock::class, fn ($e) => $e->variant->is($variant));
});

it('fires InventoryOutOfStock when reserve brings available to zero', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 5, 'reserved_quantity' => 2, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryOutOfStock::class]);

    ProductCatalog::inventory()->reserve($variant, 3); // available was 3, now 0

    Event::assertDispatched(InventoryOutOfStock::class, fn ($e) => $e->variant->is($variant));
});

it('InventoryOutOfStock carries a movement reference', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 3, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryOutOfStock::class]);

    ProductCatalog::inventory()->adjust($variant, -3);

    Event::assertDispatched(InventoryOutOfStock::class, fn ($e) => $e->movement !== null);
});

it('does not fire InventoryOutOfStock when stock stays above zero', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryOutOfStock::class]);

    ProductCatalog::inventory()->adjust($variant, -5);

    Event::assertNotDispatched(InventoryOutOfStock::class);
});

it('does not fire InventoryOutOfStock on restock', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 0, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryOutOfStock::class]);

    ProductCatalog::inventory()->adjust($variant, 10);

    Event::assertNotDispatched(InventoryOutOfStock::class);
});

it('does not fire InventoryOutOfStock on release', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 5, 'reserved_quantity' => 5, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryOutOfStock::class]);

    ProductCatalog::inventory()->release($variant, 5);

    Event::assertNotDispatched(InventoryOutOfStock::class);
});

it('does not fire InventoryOutOfStock on commit because available is unchanged', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 5, 'reserved_quantity' => 5, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryOutOfStock::class]);

    ProductCatalog::inventory()->commit($variant, 5);

    Event::assertNotDispatched(InventoryOutOfStock::class);
});

// ---------------------------------------------------------------------------
// InventoryLowStock
// ---------------------------------------------------------------------------

it('fires InventoryLowStock when adjust crosses the threshold', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'low_stock_threshold' => 3, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryLowStock::class]);

    ProductCatalog::inventory()->adjust($variant, -8); // available drops to 2, threshold is 3

    Event::assertDispatched(InventoryLowStock::class, function ($e) use ($variant) {
        return $e->variant->is($variant)
            && $e->availableQuantity === 2
            && $e->threshold === 3;
    });
});

it('fires InventoryLowStock when set crosses the threshold', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 20, 'low_stock_threshold' => 5, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryLowStock::class]);

    ProductCatalog::inventory()->set($variant, 4);

    Event::assertDispatched(InventoryLowStock::class, fn ($e) => $e->availableQuantity === 4 && $e->threshold === 5);
});

it('fires InventoryLowStock when reserve crosses the threshold', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'reserved_quantity' => 6, 'low_stock_threshold' => 5, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryLowStock::class]);

    // available was 4 (above threshold=5? No, 4 < 5 so already low)
    // Let's use: quantity=10, reserved=2, available=8, threshold=5
    // after reserve(4): available = 4 → crosses threshold
    $variant2 = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant2->id, 'quantity' => 10, 'reserved_quantity' => 2, 'low_stock_threshold' => 5, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->reserve($variant2, 4); // available: 8 → 4 (crosses threshold=5)

    Event::assertDispatched(InventoryLowStock::class, fn ($e) => $e->variant->is($variant2) && $e->availableQuantity === 4);
});

it('InventoryLowStock carries a movement reference', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'low_stock_threshold' => 5, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryLowStock::class]);

    ProductCatalog::inventory()->adjust($variant, -8);

    Event::assertDispatched(InventoryLowStock::class, fn ($e) => $e->movement !== null);
});

it('does not fire InventoryLowStock when no threshold is configured', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'low_stock_threshold' => null, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryLowStock::class]);

    ProductCatalog::inventory()->adjust($variant, -9);

    Event::assertNotDispatched(InventoryLowStock::class);
});

it('does not fire InventoryLowStock when already below threshold before the operation', function () {
    $variant = ProductVariant::factory()->create();
    // already below threshold — no crossing happens
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 3, 'low_stock_threshold' => 5, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryLowStock::class]);

    ProductCatalog::inventory()->adjust($variant, -1); // was 3 (already low), now 2

    Event::assertNotDispatched(InventoryLowStock::class);
});

it('does not fire InventoryLowStock when stock stays above threshold', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 20, 'low_stock_threshold' => 5, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryLowStock::class]);

    ProductCatalog::inventory()->adjust($variant, -5); // stays at 15, above threshold

    Event::assertNotDispatched(InventoryLowStock::class);
});

// ---------------------------------------------------------------------------
// Precedence: InventoryOutOfStock takes priority over InventoryLowStock
// ---------------------------------------------------------------------------

it('fires only InventoryOutOfStock when stock hits zero even with a threshold configured', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'low_stock_threshold' => 3, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryOutOfStock::class, InventoryLowStock::class]);

    ProductCatalog::inventory()->adjust($variant, -10); // hits exactly 0

    Event::assertDispatched(InventoryOutOfStock::class);
    Event::assertNotDispatched(InventoryLowStock::class);
});

// ---------------------------------------------------------------------------
// No events for Allow / Deny policies
// ---------------------------------------------------------------------------

it('does not fire stock alerts for Allow policy variants', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 0, 'policy' => InventoryPolicy::Allow]);

    Event::fake([InventoryOutOfStock::class, InventoryLowStock::class, InventoryAdjusted::class, InventoryReserved::class]);

    ProductCatalog::inventory()->adjust($variant, -100);

    Event::assertNotDispatched(InventoryOutOfStock::class);
    Event::assertNotDispatched(InventoryLowStock::class);
});
