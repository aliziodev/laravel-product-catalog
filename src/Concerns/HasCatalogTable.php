<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Concerns;

trait HasCatalogTable
{
    public function getTable(): string
    {
        return config('product-catalog.table_prefix', 'catalog_').$this->getCatalogTableSuffix();
    }

    abstract protected function getCatalogTableSuffix(): string;
}
