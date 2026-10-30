<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Events;

use Aliziodev\ProductCatalog\Enums\MovementType;
use Aliziodev\ProductCatalog\Models\InventoryMovement;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched after the surrounding database transaction commits, and dropped
 * if it rolls back. Without an open transaction it is dispatched immediately.
 */
class InventoryReserved implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  MovementType::Reserve|MovementType::Release  $type
     * @param  int  $quantity  Positive for Reserve, negative for Release.
     */
    public function __construct(
        public readonly ProductVariant $variant,
        public readonly MovementType $type,
        public readonly int $quantity,
        public readonly int $reservedBefore,
        public readonly int $reservedAfter,
        public readonly string $reason = '',
        public readonly ?InventoryMovement $movement = null,
    ) {}

    public function isReserve(): bool
    {
        return $this->type === MovementType::Reserve;
    }

    public function isRelease(): bool
    {
        return $this->type === MovementType::Release;
    }
}
