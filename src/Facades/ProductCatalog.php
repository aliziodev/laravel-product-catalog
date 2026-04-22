<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Facades;

use Aliziodev\ProductCatalog\Contracts\InventoryProviderInterface;
use Aliziodev\ProductCatalog\Contracts\SearchDriverInterface;
use Aliziodev\ProductCatalog\ProductCatalogManager;
use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * @method static InventoryProviderInterface inventory(?string $driver = null)
 * @method static void extend(string $driver, Closure $resolver)
 * @method static SearchDriverInterface search(?string $driver = null)
 * @method static void extendSearch(string $driver, Closure $resolver)
 *
 * @see ProductCatalogManager
 */
class ProductCatalog extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'product-catalog';
    }
}
