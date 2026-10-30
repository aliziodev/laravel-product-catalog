<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Aliziodev\ProductCatalog\Events\InventoryAdjusted;
use Aliziodev\ProductCatalog\Events\InventoryOutOfStock;
use Aliziodev\ProductCatalog\Events\InventoryReserved;
use Aliziodev\ProductCatalog\Events\ProductPublished;
use Aliziodev\ProductCatalog\Facades\ProductCatalog;
use Aliziodev\ProductCatalog\Models\InventoryItem;
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Record the class of every listed event as it reaches its listeners.
 *
 * @param  list<class-string>  $events
 */
function recordDispatchedEvents(array $events): ArrayObject
{
    $dispatched = new ArrayObject;

    foreach ($events as $event) {
        Event::listen($event, fn (object $payload) => $dispatched->append($payload::class));
    }

    return $dispatched;
}

it('holds inventory events until the surrounding transaction commits', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 3, 'policy' => InventoryPolicy::Track]);

    $dispatched = recordDispatchedEvents([InventoryReserved::class, InventoryOutOfStock::class]);

    DB::transaction(function () use ($variant, $dispatched) {
        ProductCatalog::inventory()->reserve($variant, 3);

        expect($dispatched->getArrayCopy())->toBe([]);
    });

    expect($dispatched->getArrayCopy())->toBe([InventoryReserved::class, InventoryOutOfStock::class]);
});

it('drops inventory events when the surrounding transaction rolls back', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'policy' => InventoryPolicy::Track]);

    $dispatched = recordDispatchedEvents([InventoryAdjusted::class]);

    try {
        DB::transaction(function () use ($variant) {
            ProductCatalog::inventory()->adjust($variant, -4, 'sale');

            throw new RuntimeException('Payment failed');
        });
    } catch (RuntimeException) {
        // The order failed after the stock change; the change was rolled back.
    }

    expect($dispatched->getArrayCopy())->toBe([])
        ->and(InventoryItem::where('variant_id', $variant->id)->value('quantity'))->toBe(10);
});

it('dispatches inventory events right away when no outer transaction is open', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'policy' => InventoryPolicy::Track]);

    $dispatched = recordDispatchedEvents([InventoryAdjusted::class]);

    ProductCatalog::inventory()->adjust($variant, 5, 'purchase');

    expect($dispatched->getArrayCopy())->toBe([InventoryAdjusted::class]);
});

it('holds ProductPublished until the surrounding transaction commits', function () {
    $product = Product::factory()->create();

    $dispatched = recordDispatchedEvents([ProductPublished::class]);

    try {
        DB::transaction(function () use ($product) {
            $product->publish();

            throw new RuntimeException('Rolled back');
        });
    } catch (RuntimeException) {
        // Publishing was part of a larger change that failed.
    }

    expect($dispatched->getArrayCopy())->toBe([]);

    DB::transaction(fn () => $product->refresh()->publish());

    expect($dispatched->getArrayCopy())->toBe([ProductPublished::class]);
});
