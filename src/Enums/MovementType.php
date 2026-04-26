<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Enums;

enum MovementType: string
{
    /** Stock added — purchase, restock, return. */
    case Restock = 'restock';

    /** Stock removed — sale, damage, expiry. */
    case Deduction = 'deduction';

    /** Manual delta correction (positive or negative). */
    case Adjustment = 'adjustment';

    /** Absolute quantity override via set(). */
    case Set = 'set';

    /** reserved_quantity increased — order placed, cart hold. Total quantity unchanged. */
    case Reserve = 'reserve';

    /** reserved_quantity decreased — order cancelled, cart released. Total quantity unchanged. */
    case Release = 'release';
}
