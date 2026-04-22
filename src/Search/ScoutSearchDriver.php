<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Search;

use Aliziodev\ProductCatalog\Contracts\SearchDriverInterface;
use Aliziodev\ProductCatalog\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Search driver that delegates text search to Laravel Scout (Algolia, Meilisearch,
 * Typesense, or the Scout database engine), then applies catalog-specific filters
 * and sorting via an Eloquent constraint callback.
 *
 * Requirements:
 *  - laravel/scout ^10.0 installed
 *  - The Product model (or your extension of it) must use Laravel\Scout\Searchable
 *
 * Pagination note: Scout paginates over the raw engine results before Eloquent
 * post-filtering is applied. If your Eloquent filters remove many results, prefer
 * DatabaseSearchDriver for accurate pagination counts.
 */
class ScoutSearchDriver implements SearchDriverInterface
{
    public function __construct(private readonly DatabaseSearchDriver $filterDriver)
    {
        if (! $this->scoutIsInstalled()) {
            throw new RuntimeException(
                'ScoutSearchDriver requires laravel/scout. Install it with: composer require laravel/scout'
            );
        }
    }

    public function paginate(string $query, array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator */
        return $this->buildScoutQuery($query, $filters)->paginate($perPage, 'page', $page);
    }

    public function get(string $query, array $filters): Collection
    {
        /** @var Collection<int, Product> */
        return $this->buildScoutQuery($query, $filters)->get();
    }

    protected function scoutIsInstalled(): bool
    {
        return class_exists(\Laravel\Scout\Builder::class);
    }

    protected function searchableModelClass(): string
    {
        $modelClass = config('product-catalog.search.model', Product::class);

        if (! is_string($modelClass) || $modelClass === '' || ! class_exists($modelClass)) {
            throw new RuntimeException('ScoutSearchDriver search model is not configured correctly.');
        }

        if (! method_exists($modelClass, 'search')) {
            throw new RuntimeException(
                'ScoutSearchDriver requires the configured search model to use Laravel\Scout\Searchable.'
            );
        }

        return $modelClass;
    }

    protected function makeScoutBuilder(string $query): object
    {
        $modelClass = $this->searchableModelClass();

        return $modelClass::search($query);
    }

    private function buildScoutQuery(string $query, array $filters): object
    {
        $sortBy = $filters['sort_by'] ?? null;
        $dir = strtolower($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $filterDriver = $this->filterDriver;

        return $this->makeScoutBuilder($query)->query(
            function (Builder $eloquentBuilder) use ($filters, $filterDriver, $sortBy, $dir) {
                $filterDriver->applyFilters($eloquentBuilder, $filters);

                // Apply sort at Eloquent level when explicitly requested.
                // When $sortBy is null, Scout's engine-native relevance ranking is used.
                if ($sortBy !== null) {
                    $filterDriver->applySort($eloquentBuilder, $sortBy, $dir);
                }
            }
        );
    }
}
