<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Exceptions;

use RuntimeException;

class ProductCatalogException extends RuntimeException
{
    public static function driverNotFound(string $driver): static
    {
        return new static("Inventory driver [{$driver}] is not registered in ProductCatalog.");
    }

    public static function invalidProductType(string $type): static
    {
        return new static("Product type [{$type}] is invalid.");
    }

    public static function cannotPublish(string $reason): static
    {
        return new static("Product cannot be published: {$reason}");
    }
}
