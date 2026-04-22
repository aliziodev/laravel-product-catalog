<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Drivers\DatabaseInventoryProvider;
use Aliziodev\ProductCatalog\Drivers\NullInventoryProvider;
use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Aliziodev\ProductCatalog\Events\InventoryAdjusted;
use Aliziodev\ProductCatalog\Exceptions\InventoryException;
use Aliziodev\ProductCatalog\Exceptions\ProductCatalogException;
use Aliziodev\ProductCatalog\Facades\ProductCatalog;
use Aliziodev\ProductCatalog\Models\InventoryItem;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Support\Facades\Event;

it('resolves database driver by default', function () {
    expect(ProductCatalog::inventory())->toBeInstanceOf(DatabaseInventoryProvider::class);
});

it('resolves null driver by name', function () {
    expect(ProductCatalog::inventory('null'))->toBeInstanceOf(NullInventoryProvider::class);
});

it('resolves a custom driver registered via extend', function () {
    ProductCatalog::extend('custom', fn () => new NullInventoryProvider);

    expect(ProductCatalog::inventory('custom'))->toBeInstanceOf(NullInventoryProvider::class);
});

it('throws when an unknown driver is requested', function () {
    ProductCatalog::inventory('nonexistent');
})->throws(ProductCatalogException::class);

// --- DatabaseInventoryProvider ---

it('creates an inventory item on first access', function () {
    $variant = ProductVariant::factory()->create();

    expect(InventoryItem::count())->toBe(0);

    ProductCatalog::inventory()->getQuantity($variant);

    expect(InventoryItem::count())->toBe(1);
});

it('reports in stock when quantity is sufficient', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 5, 'policy' => InventoryPolicy::Track]);

    expect(ProductCatalog::inventory()->isInStock($variant))->toBeTrue()
        ->and(ProductCatalog::inventory()->canFulfill($variant, 5))->toBeTrue()
        ->and(ProductCatalog::inventory()->canFulfill($variant, 6))->toBeFalse();
});

it('always returns in stock for allow policy', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 0, 'policy' => InventoryPolicy::Allow]);

    expect(ProductCatalog::inventory()->isInStock($variant))->toBeTrue()
        ->and(ProductCatalog::inventory()->getQuantity($variant))->toBe(PHP_INT_MAX);
});

it('always returns out of stock for deny policy', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 100, 'policy' => InventoryPolicy::Deny]);

    expect(ProductCatalog::inventory()->isInStock($variant))->toBeFalse()
        ->and(ProductCatalog::inventory()->getQuantity($variant))->toBe(0);
});

it('adjusts stock and fires event', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 10, 'policy' => InventoryPolicy::Track]);

    Event::fake([InventoryAdjusted::class]);

    ProductCatalog::inventory()->adjust($variant, -3, 'sale');

    expect(InventoryItem::where('variant_id', $variant->id)->value('quantity'))->toBe(7);
    Event::assertDispatched(InventoryAdjusted::class, fn ($e) => $e->previousQuantity === 10 && $e->newQuantity === 7);
});

it('throws on insufficient stock during adjustment', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 2, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->adjust($variant, -5);
})->throws(InventoryException::class);

it('sets absolute stock quantity', function () {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'quantity' => 5, 'policy' => InventoryPolicy::Track]);

    ProductCatalog::inventory()->set($variant, 100);

    expect(InventoryItem::where('variant_id', $variant->id)->value('quantity'))->toBe(100);
});

it('throws when setting negative quantity', function () {
    $variant = ProductVariant::factory()->create();

    ProductCatalog::inventory()->set($variant, -1);
})->throws(InventoryException::class);

// --- NullInventoryProvider ---

it('null provider is always in stock and ignores adjustments', function () {
    $variant = ProductVariant::factory()->create();
    $provider = new NullInventoryProvider;

    expect($provider->isInStock($variant))->toBeTrue()
        ->and($provider->getQuantity($variant))->toBe(PHP_INT_MAX)
        ->and($provider->canFulfill($variant, 999999))->toBeTrue();

    $provider->adjust($variant, -100); // should not throw
    $provider->set($variant, 0);       // should not throw
});
