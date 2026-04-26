<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Events;

use Aliziodev\ProductCatalog\Models\InventoryMovement;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryOutOfStock
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ProductVariant $variant,
        public readonly ?InventoryMovement $movement = null,
    ) {}
}
