<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Aliziodev\ProductCatalog\Models\InventoryItem;
use Aliziodev\ProductCatalog\Models\ProductVariant;

// --- variant() relationship ---

it('inventoryItem belongs to a variant', function () {
    $variant = ProductVariant::factory()->create();
    $item = InventoryItem::factory()->create(['variant_id' => $variant->id]);

    expect($item->variant->id)->toBe($variant->id);
});

// --- isTracked() ---

it('isTracked returns true when policy is track', function () {
    $item = InventoryItem::factory()->create(['policy' => InventoryPolicy::Track]);

    expect($item->isTracked())->toBeTrue();
});

it('isTracked returns false when policy is allow', function () {
    $item = InventoryItem::factory()->create(['policy' => InventoryPolicy::Allow]);

    expect($item->isTracked())->toBeFalse();
});

it('isTracked returns false when policy is deny', function () {
    $item = InventoryItem::factory()->create(['policy' => InventoryPolicy::Deny]);

    expect($item->isTracked())->toBeFalse();
});

// --- isPurchaseAllowed() ---

it('isPurchaseAllowed returns true when policy is track', function () {
    $item = InventoryItem::factory()->create(['policy' => InventoryPolicy::Track]);

    expect($item->isPurchaseAllowed())->toBeTrue();
});

it('isPurchaseAllowed returns true when policy is allow', function () {
    $item = InventoryItem::factory()->create(['policy' => InventoryPolicy::Allow]);

    expect($item->isPurchaseAllowed())->toBeTrue();
});

it('isPurchaseAllowed returns false when policy is deny', function () {
    $item = InventoryItem::factory()->create(['policy' => InventoryPolicy::Deny]);

    expect($item->isPurchaseAllowed())->toBeFalse();
});
