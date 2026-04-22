<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Search;

use Aliziodev\ProductCatalog\Contracts\SearchDriverInterface;
use Aliziodev\ProductCatalog\Enums\ProductStatus;
use Aliziodev\ProductCatalog\Enums\ProductType;
use Aliziodev\ProductCatalog\Models\Category;
use Aliziodev\ProductCatalog\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DatabaseSearchDriver implements SearchDriverInterface
{
    public function paginate(string $query, array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->buildQuery($query, $filters)->paginate($perPage, ['*'], 'page', $page);
    }

    public function get(string $query, array $filters): Collection
    {
        return $this->buildQuery($query, $filters)->get();
    }

    protected function buildQuery(string $query, array $filters): Builder
    {
        // Respect caller-supplied eager-load list; fall back to sensible defaults.
        $relations = $filters['_with'] ?? ['defaultVariant', 'brand', 'primaryCategory'];
        $builder = Product::query()->with($relations);

        $this->applyFilters($builder, $filters);

        if ($query !== '') {
            $this->applyTextSearch($builder, $query);
        }

        $this->applySort(
            $builder,
            $filters['sort_by'] ?? 'newest',
            strtolower($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc',
        );

        return $builder;
    }

    /**
     * Apply non-text, non-sort filters to an existing Eloquent builder.
     * Called directly by ScoutSearchDriver via the ->query() callback.
     *
     * @param  array<string, mixed>  $filters
     */
    public function applyFilters(Builder $builder, array $filters): void
    {
        // Status (default: published)
        $status = isset($filters['status'])
            ? ProductStatus::from($filters['status'])
            : ProductStatus::Published;

        $builder->where('status', $status->value);

        if (! empty($filters['category'])) {
            $this->applyCategory($builder, $filters['category']);
        }

        if (! empty($filters['brand'])) {
            $this->applyBrand($builder, $filters['brand']);
        }

        if (! empty($filters['tags'])) {
            $this->applyTags($builder, (array) $filters['tags']);
        }

        if (isset($filters['min_price']) || isset($filters['max_price'])) {
            $this->applyPriceRange(
                $builder,
                isset($filters['min_price']) ? (float) $filters['min_price'] : null,
                isset($filters['max_price']) ? (float) $filters['max_price'] : null,
            );
        }

        if (! empty($filters['in_stock'])) {
            $builder->inStock();
        }

        if (! empty($filters['type'])) {
            $builder->where('type', ProductType::from($filters['type'])->value);
        }
    }

    /**
     * Apply sort to an Eloquent builder.
     * Exposed as public so ScoutSearchDriver can reuse it.
     */
    public function applySort(Builder $builder, string $sortBy, string $direction): void
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');
        $dir = $direction === 'asc' ? 'asc' : 'desc';

        match ($sortBy) {
            'price' => $builder->orderByRaw(
                "(SELECT MIN(price) FROM {$prefix}product_variants
                  WHERE product_id = {$prefix}products.id AND is_active = 1) {$dir}"
            ),
            'name' => $builder->orderBy('name', $dir),
            'oldest' => $builder->orderBy('published_at', 'asc'),
            default => $builder->orderBy('published_at', 'desc'),
        };
    }

    /**
     * Apply text search using FULLTEXT or LIKE depending on configuration.
     *
     * FULLTEXT mode (search.fulltext = true):
     *   - Requires MySQL/MariaDB with a FULLTEXT index on (name, short_description).
     *   - Much faster on large tables; supports relevance ranking.
     *   - NOT supported by SQLite — keep this false in your testing environment.
     *
     * LIKE mode (default):
     *   - Works on all databases including SQLite.
     *   - LIKE "%term%" cannot use B-tree indexes → full table scan on large datasets.
     *   - Sufficient for catalogs up to ~10k products.
     *
     * Note: neither mode searches variant option values (e.g. colour "Midnight").
     * Option-value search requires a dedicated search engine (ScoutSearchDriver).
     */
    private function applyTextSearch(Builder $builder, string $query): void
    {
        if (config('product-catalog.search.fulltext', false)) {
            $prefix = config('product-catalog.table_prefix', 'catalog_');
            $table = "{$prefix}products";

            // MATCH AGAINST searches name + short_description via the FULLTEXT index.
            // SKU search falls back to LIKE since variant SKUs are in a separate table.
            $builder->where(function (Builder $q) use ($table, $query) {
                $q->whereFullText(["{$table}.name", "{$table}.short_description"], $query)
                    ->orWhereHas(
                        'variants',
                        fn (Builder $v) => $v->where('sku', 'like', "%{$query}%")
                    );
            });
        } else {
            // Uses Product::scopeSearch() — LIKE on name, code, short_description, sku.
            $builder->search($query);
        }
    }

    private function applyCategory(Builder $builder, int|string $category): void
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        if (is_int($category)) {
            $categoryId = $category;
        } else {
            $categoryId = Category::where('slug', $category)->value('id');

            if (! $categoryId) {
                $builder->whereRaw('1 = 0');

                return;
            }
        }

        $builder->where(function (Builder $q) use ($prefix, $categoryId) {
            $q->where('primary_category_id', $categoryId)
                ->orWhereExists(function ($sub) use ($prefix, $categoryId) {
                    $sub->from($prefix.'product_categories')
                        ->whereColumn("{$prefix}product_categories.product_id", "{$prefix}products.id")
                        ->where("{$prefix}product_categories.category_id", $categoryId);
                });
        });
    }

    private function applyBrand(Builder $builder, int|string $brand): void
    {
        if (is_int($brand)) {
            $builder->where('brand_id', $brand);
        } else {
            $builder->whereHas('brand', fn (Builder $q) => $q->where('slug', $brand));
        }
    }

    /**
     * @param  array<int|string>  $tags
     */
    private function applyTags(Builder $builder, array $tags): void
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        foreach ($tags as $tag) {
            $builder->whereExists(function ($sub) use ($prefix, $tag) {
                $sub->from($prefix.'product_tags')
                    ->join(
                        $prefix.'tags',
                        "{$prefix}tags.id",
                        '=',
                        "{$prefix}product_tags.tag_id"
                    )
                    ->whereColumn("{$prefix}product_tags.product_id", "{$prefix}products.id")
                    ->whereNull("{$prefix}tags.deleted_at");

                is_int($tag)
                    ? $sub->where("{$prefix}tags.id", $tag)
                    : $sub->where("{$prefix}tags.slug", $tag);
            });
        }
    }

    private function applyPriceRange(Builder $builder, ?float $min, ?float $max): void
    {
        $builder->whereHas('variants', function (Builder $q) use ($min, $max) {
            $q->where('is_active', true);

            if ($min !== null) {
                $q->where('price', '>=', $min);
            }

            if ($max !== null) {
                $q->where('price', '<=', $max);
            }
        });
    }
}
