<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Events;

use Aliziodev\ProductCatalog\Models\InventoryMovement;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after the surrounding database transaction commits, and dropped
 * if it rolls back. Without an open transaction it is dispatched immediately.
 */
class InventoryLowStock implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ProductVariant $variant,
        public readonly int $availableQuantity,
        public readonly int $threshold,
        public readonly ?InventoryMovement $movement = null,
    ) {}
}
