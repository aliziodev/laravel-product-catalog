<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Enums;

enum ProductType: string
{
    /** A product with a single variant (no options). */
    case Simple = 'simple';

    /** A product with multiple variants defined by option combinations. */
    case Variable = 'variable';
}
