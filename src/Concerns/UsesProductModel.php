<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Concerns;

use Aliziodev\ProductCatalog\Models\Product;

/**
 * Resolves the product model from `product-catalog.model`, so relations
 * return the application's extended Product instead of the base class.
 */
trait UsesProductModel
{
    /**
     * @return class-string<Product>
     */
    protected function productModel(): string
    {
        return config('product-catalog.model', Product::class);
    }
}
