<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Drivers;

use Aliziodev\ProductCatalog\Contracts\InventoryProviderInterface;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;

/**
 * Always-in-stock provider. Suitable for digital products or
 * when inventory tracking is not required.
 */
class NullInventoryProvider implements InventoryProviderInterface
{
    public function getQuantity(ProductVariant $variant): int
    {
        return PHP_INT_MAX;
    }

    public function isInStock(ProductVariant $variant): bool
    {
        return true;
    }

    public function canFulfill(ProductVariant $variant, int $quantity): bool
    {
        return true;
    }

    public function adjust(
        ProductVariant $variant,
        int $delta,
        string $reason = '',
        ?Model $reference = null,
    ): void {
        // no-op
    }

    public function set(
        ProductVariant $variant,
        int $quantity,
        string $reason = '',
        ?Model $reference = null,
    ): void {
        // no-op
    }

    public function reserve(
        ProductVariant $variant,
        int $quantity,
        string $reason = '',
        ?Model $reference = null,
    ): void {
        // no-op
    }

    public function release(
        ProductVariant $variant,
        int $quantity,
        string $reason = '',
        ?Model $reference = null,
    ): void {
        // no-op
    }

    public function commit(
        ProductVariant $variant,
        int $quantity,
        string $reason = '',
        ?Model $reference = null,
    ): void {
        // no-op
    }
}
