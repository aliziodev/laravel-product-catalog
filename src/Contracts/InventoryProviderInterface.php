<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Contracts;

use Aliziodev\ProductCatalog\Exceptions\InventoryException;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;

interface InventoryProviderInterface
{
    /**
     * Return the available stock quantity for the given variant.
     *
     * "Available" means physical quantity minus any reserved quantity.
     * Returns PHP_INT_MAX for unlimited-stock drivers (Allow policy).
     * Returns 0 for drivers that always deny purchase (Deny policy).
     */
    public function getQuantity(ProductVariant $variant): int;

    /**
     * Determine whether the variant can be purchased.
     */
    public function isInStock(ProductVariant $variant): bool;

    /**
     * Determine whether a specific quantity can be fulfilled.
     */
    public function canFulfill(ProductVariant $variant, int $quantity): bool;

    /**
     * Adjust stock by a positive (restock) or negative (deduct) delta.
     *
     * @param  Model|null  $reference  Optional source model (Order, PurchaseOrder, etc.)
     *
     * @throws InventoryException
     */
    public function adjust(
        ProductVariant $variant,
        int $delta,
        string $reason = '',
        ?Model $reference = null,
    ): void;

    /**
     * Set the absolute stock quantity for the variant.
     *
     * @param  Model|null  $reference  Optional source model
     *
     * @throws InventoryException
     */
    public function set(
        ProductVariant $variant,
        int $quantity,
        string $reason = '',
        ?Model $reference = null,
    ): void;
}
