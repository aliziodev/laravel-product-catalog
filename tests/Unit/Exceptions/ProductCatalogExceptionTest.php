<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Exceptions\ProductCatalogException;

it('creates driverNotFound exception with correct message', function () {
    $e = ProductCatalogException::driverNotFound('redis');

    expect($e)->toBeInstanceOf(ProductCatalogException::class)
        ->and($e->getMessage())->toBe('Inventory driver [redis] is not registered in ProductCatalog.');
});

it('creates invalidProductType exception with correct message', function () {
    $e = ProductCatalogException::invalidProductType('bundle');

    expect($e)->toBeInstanceOf(ProductCatalogException::class)
        ->and($e->getMessage())->toBe('Product type [bundle] is invalid.');
});

it('creates cannotPublish exception with correct message', function () {
    $e = ProductCatalogException::cannotPublish('no active variants');

    expect($e)->toBeInstanceOf(ProductCatalogException::class)
        ->and($e->getMessage())->toBe('Product cannot be published: no active variants');
});
