<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Contracts;

use Aliziodev\ProductCatalog\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SearchDriverInterface
{
    /**
     * Return a paginated result set for the given query and filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(string $query, array $filters, int $perPage, int $page): LengthAwarePaginator;

    /**
     * Return all matching products without pagination.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Product>
     */
    public function get(string $query, array $filters): Collection;
}
