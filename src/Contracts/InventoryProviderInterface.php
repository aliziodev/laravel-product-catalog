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

    /**
     * Reserve stock for a pending order or cart hold.
     *
     * Increments reserved_quantity without touching total quantity.
     * Available quantity (total − reserved) is reduced immediately,
     * preventing double-selling while awaiting payment.
     * Only applies to Track policy; no-op otherwise.
     *
     * @param  Model|null  $reference  Optional source model (Order, Cart, etc.)
     *
     * @throws InventoryException when available stock < quantity
     */
    public function reserve(
        ProductVariant $variant,
        int $quantity,
        string $reason = '',
        ?Model $reference = null,
    ): void;

    /**
     * Release a previously reserved quantity back to available.
     *
     * Decrements reserved_quantity (capped at current reserved).
     * Use when an order is cancelled or a cart hold expires.
     * Only applies to Track policy; no-op otherwise.
     *
     * @param  Model|null  $reference  Optional source model
     */
    public function release(
        ProductVariant $variant,
        int $quantity,
        string $reason = '',
        ?Model $reference = null,
    ): void;

    /**
     * Commit a reservation as a permanent stock deduction.
     *
     * Decrements both quantity and reserved_quantity by the given amount.
     * Use when an order is fulfilled and the reserved stock is consumed.
     * Only applies to Track policy; no-op otherwise.
     *
     * @param  Model|null  $reference  Optional source model
     *
     * @throws InventoryException when reserved_quantity < quantity
     */
    public function commit(
        ProductVariant $variant,
        int $quantity,
        string $reason = '',
        ?Model $reference = null,
    ): void;
}
