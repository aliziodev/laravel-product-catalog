<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Events;

use Aliziodev\ProductCatalog\Enums\MovementType;
use Aliziodev\ProductCatalog\Models\InventoryMovement;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryReserved
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
