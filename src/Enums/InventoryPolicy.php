<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Enums;

enum InventoryPolicy: string
{
    /** Track quantity; deny purchase when stock reaches zero. */
    case Track = 'track';

    /** Never allow purchase regardless of stock level. */
    case Deny = 'deny';

    /** Always allow purchase; overselling is permitted. */
    case Allow = 'allow';
}
