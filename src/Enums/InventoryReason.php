<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Enums;

/**
 * Preset reason strings for inventory movements.
 *
 * Use these constants as the `reason` argument to keep audit trails
 * consistent across your application. Add your own reason strings in
 * config('product-catalog.inventory.movement_reasons') and reference
 * them via your own constants — do not pass free-form strings.
 *
 * Mapping guide (type → reasons):
 *   Restock    → PURCHASE, RETURN_ITEM
 *   Deduction  → SALE, DAMAGE, EXPIRY, ORDER_FULFILLED
 *   Adjustment → CORRECTION
 *   Set        → STOCKTAKE
 *   Reserve    → ORDER_PLACED, CART_HOLD
 *   Release    → ORDER_CANCELLED, CART_RELEASED, TIMEOUT
 */
final class InventoryReason
{
    // --- Restock ---
    public const PURCHASE = 'purchase';

    public const RETURN_ITEM = 'return';

    // --- Deduction ---
    public const SALE = 'sale';

    public const DAMAGE = 'damage';

    public const EXPIRY = 'expiry';

    // --- Adjustment / Set ---
    public const CORRECTION = 'correction';

    public const STOCKTAKE = 'stocktake';

    // --- Reserve ---
    public const ORDER_PLACED = 'order_placed';

    public const CART_HOLD = 'cart_hold';

    // --- Release ---
    public const ORDER_CANCELLED = 'order_cancelled';

    public const CART_RELEASED = 'cart_released';

    public const TIMEOUT = 'timeout';

    // --- Commit (Deduction that also releases a reservation) ---
    public const ORDER_FULFILLED = 'order_fulfilled';
}
