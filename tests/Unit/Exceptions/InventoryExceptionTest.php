<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Exceptions\InventoryException;

it('creates insufficientStock exception with correct message', function () {
    $e = InventoryException::insufficientStock(10, 3);

    expect($e)->toBeInstanceOf(InventoryException::class)
        ->and($e->getMessage())->toBe('Insufficient stock: requested 10, available 3.');
});

it('creates purchaseNotAllowed exception with correct message', function () {
    $e = InventoryException::purchaseNotAllowed(42);

    expect($e)->toBeInstanceOf(InventoryException::class)
        ->and($e->getMessage())->toBe('Variant [42] has purchase policy set to deny.');
});

it('creates negativeQuantityNotAllowed exception with correct message', function () {
    $e = InventoryException::negativeQuantityNotAllowed();

    expect($e)->toBeInstanceOf(InventoryException::class)
        ->and($e->getMessage())->toBe('Stock quantity cannot be set to a negative value.');
});
