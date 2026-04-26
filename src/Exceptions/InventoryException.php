<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Exceptions;

use RuntimeException;

class InventoryException extends RuntimeException
{
    public static function insufficientStock(int $requested, int $available): static
    {
        return new static(
            "Insufficient stock: requested {$requested}, available {$available}."
        );
    }

    public static function purchaseNotAllowed(int $variantId): static
    {
        return new static("Variant [{$variantId}] has purchase policy set to deny.");
    }

    public static function negativeQuantityNotAllowed(): static
    {
        return new static('Stock quantity cannot be set to a negative value.');
    }

    public static function insufficientReservation(int $requested, int $reserved): static
    {
        return new static(
            "Insufficient reservation: requested {$requested} to commit, only {$reserved} reserved."
        );
    }
}
