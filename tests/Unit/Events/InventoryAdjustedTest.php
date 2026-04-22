<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Events\InventoryAdjusted;
use Aliziodev\ProductCatalog\Models\ProductVariant;

it('delta returns positive value when stock increased', function () {
    $variant = ProductVariant::factory()->create();
    $event = new InventoryAdjusted($variant, previousQuantity: 10, newQuantity: 15);

    expect($event->delta())->toBe(5);
});

it('delta returns negative value when stock decreased', function () {
    $variant = ProductVariant::factory()->create();
    $event = new InventoryAdjusted($variant, previousQuantity: 20, newQuantity: 13);

    expect($event->delta())->toBe(-7);
});

it('delta returns zero when quantity unchanged', function () {
    $variant = ProductVariant::factory()->create();
    $event = new InventoryAdjusted($variant, previousQuantity: 5, newQuantity: 5);

    expect($event->delta())->toBe(0);
});
