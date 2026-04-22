<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Search;

use Aliziodev\ProductCatalog\Contracts\SearchDriverInterface;
use Aliziodev\ProductCatalog\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Fluent catalog-aware search builder.
 *
 * Usage:
 *
 *   use Aliziodev\ProductCatalog\Search\ProductSearchBuilder;
 *
 *   ProductSearchBuilder::query('kemeja')
 *       ->inCategory('t-shirts')
 *       ->withTags(['sale', 'new-arrival'])
 *       ->forBrand('stylehouse')
 *       ->priceBetween(100_000, 500_000)
 *       ->onlyInStock()
 *       ->sortBy('price')
 *       ->paginate(24);
 *
 *   // Or build from HTTP request directly:
 *   ProductSearchBuilder::fromRequest($request)->paginate(24);
 */
class ProductSearchBuilder
{
    protected string $query = '';

    /** @var array<string, mixed> */
    protected array $filters = [
        'status' => 'published',
        'category' => null,
        'tags' => [],
        'brand' => null,
        'min_price' => null,
        'max_price' => null,
        'in_stock' => false,
        'type' => null,
        'sort_by' => null,
        'sort_direction' => 'desc',
    ];

    /**
     * Relations to eager-load on the result set.
     * Passed to the driver as the '_with' filter key.
     *
     * @var array<string>
     */
    protected array $relations = ['defaultVariant', 'brand', 'primaryCategory'];

    protected ?SearchDriverInterface $driverOverride = null;

    // -------------------------------------------------------------------------
    // Entry points
    // -------------------------------------------------------------------------

    /**
     * Start a new search. Pass an empty string to list all products.
     */
    public static function query(string $query = ''): static
    {
        $instance = new static;
        $instance->query = $query;

        return $instance;
    }

    /**
     * Build a search from an incoming HTTP request.
     *
     * Recognized query-string parameters:
     *   q / search      → text query
     *   category        → slug or numeric ID
     *   brand           → slug or numeric ID
     *   tags[]          → array of slugs or numeric IDs
     *   tag             → single slug or numeric ID (backward-compat with old API)
     *   min_price       → float
     *   max_price       → float
     *   in_stock        → boolean (truthy: "1", "true", "yes", "on")
     *   type            → "simple" | "variable"
     *   sort_by         → "price" | "name" | "newest" | "oldest"
     *   sort_direction  → "asc" | "desc"
     *   status          → overrides default "published" filter
     */
    public static function fromRequest(Request $request): static
    {
        $instance = static::query(
            trim($request->string('q', $request->string('search', ''))->value())
        );

        if ($request->filled('category')) {
            $instance->inCategory(static::castIdOrSlug($request->input('category')));
        }

        if ($request->filled('brand')) {
            $instance->forBrand(static::castIdOrSlug($request->input('brand')));
        }

        // Support both ?tags[]=a&tags[]=b and the legacy ?tag=<id>
        if ($request->filled('tags')) {
            $instance->withTags(static::castTags((array) $request->input('tags')));
        } elseif ($request->filled('tag')) {
            $instance->withTags([static::castIdOrSlug($request->input('tag'))]);
        }

        if ($request->filled('min_price')) {
            $instance->minPrice((float) $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $instance->maxPrice((float) $request->input('max_price'));
        }

        if ($request->boolean('in_stock')) {
            $instance->onlyInStock();
        }

        if ($request->filled('type')) {
            $instance->ofType($request->input('type'));
        }

        if ($request->filled('sort_by')) {
            $instance->sortBy($request->input('sort_by'));
        }

        if ($request->input('sort_direction') === 'asc') {
            $instance->sortAscending();
        }

        if ($request->filled('status')) {
            $instance->withStatus($request->input('status'));
        }

        return $instance;
    }

    // -------------------------------------------------------------------------
    // Filter chain
    // -------------------------------------------------------------------------

    /**
     * Filter by category slug or ID.
     * Matches products whose primary category OR any assigned category matches.
     */
    public function inCategory(int|string $category): static
    {
        $this->filters['category'] = $category;

        return $this;
    }

    /**
     * Filter by one or more tag slugs or IDs.
     * Products must have ALL given tags (AND logic).
     *
     * @param  array<int|string>  $tags
     */
    public function withTags(array $tags): static
    {
        $this->filters['tags'] = $tags;

        return $this;
    }

    /**
     * Filter by brand slug or ID.
     */
    public function forBrand(int|string $brand): static
    {
        $this->filters['brand'] = $brand;

        return $this;
    }

    /**
     * Filter by price range (inclusive, matched against active variant prices).
     */
    public function priceBetween(float $min, float $max): static
    {
        $this->filters['min_price'] = $min;
        $this->filters['max_price'] = $max;

        return $this;
    }

    /**
     * Filter by minimum price.
     */
    public function minPrice(float $min): static
    {
        $this->filters['min_price'] = $min;

        return $this;
    }

    /**
     * Filter by maximum price.
     */
    public function maxPrice(float $max): static
    {
        $this->filters['max_price'] = $max;

        return $this;
    }

    /**
     * Restrict results to products that have at least one active variant in stock.
     */
    public function onlyInStock(): static
    {
        $this->filters['in_stock'] = true;

        return $this;
    }

    /**
     * Filter by product type ('simple' or 'variable').
     */
    public function ofType(string $type): static
    {
        $this->filters['type'] = $type;

        return $this;
    }

    /**
     * Override the default 'published' status filter.
     */
    public function withStatus(string $status): static
    {
        $this->filters['status'] = $status;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Sort chain
    // -------------------------------------------------------------------------

    /**
     * Sort results by a field.
     *
     * Supported values: 'price', 'name', 'newest' (default), 'oldest'.
     * For ScoutSearchDriver with no explicit sort, the engine's relevance ranking is used.
     */
    public function sortBy(string $field): static
    {
        $this->filters['sort_by'] = $field;

        return $this;
    }

    /** Sort in ascending order (use after sortBy). */
    public function sortAscending(): static
    {
        $this->filters['sort_direction'] = 'asc';

        return $this;
    }

    /** Sort in descending order (use after sortBy). This is the default. */
    public function sortDescending(): static
    {
        $this->filters['sort_direction'] = 'desc';

        return $this;
    }

    // -------------------------------------------------------------------------
    // Eager loading
    // -------------------------------------------------------------------------

    /**
     * Override the default eager-loaded relations on the result set.
     *
     * Default: ['defaultVariant', 'brand', 'primaryCategory']
     *
     * Pass an empty array to disable eager loading entirely.
     *
     * @param  array<string>  $relations
     */
    public function withRelations(array $relations): static
    {
        $this->relations = $relations;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Driver override
    // -------------------------------------------------------------------------

    /**
     * Use a specific driver instance instead of the configured default.
     */
    public function usingDriver(SearchDriverInterface $driver): static
    {
        $this->driverOverride = $driver;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Execute
    // -------------------------------------------------------------------------

    /** Execute the search and return a paginated result. */
    public function paginate(int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return $this->driver()->paginate($this->query, $this->compactFilters(), $perPage, $page);
    }

    /**
     * Execute the search and return all matching products.
     *
     * @return Collection<int, Product>
     */
    public function get(): Collection
    {
        return $this->driver()->get($this->query, $this->compactFilters());
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    protected function driver(): SearchDriverInterface
    {
        return $this->driverOverride ?? app(SearchDriverInterface::class);
    }

    /**
     * Strip null / empty values so drivers can use isset() checks cleanly.
     * '_with' is always included so drivers can control eager loading.
     *
     * @return array<string, mixed>
     */
    protected function compactFilters(): array
    {
        $filters = array_filter(
            $this->filters,
            fn ($v) => $v !== null && $v !== [] && $v !== false,
        );

        $filters['_with'] = $this->relations;

        return $filters;
    }

    /**
     * Cast a request value to int if it looks like a numeric ID, otherwise keep as slug.
     */
    protected static function castIdOrSlug(mixed $value): int|string
    {
        return ctype_digit((string) $value) ? (int) $value : (string) $value;
    }

    /**
     * Cast an array of tag values: numeric strings → int, others → slug string.
     *
     * @param  array<mixed>  $tags
     * @return array<int|string>
     */
    protected static function castTags(array $tags): array
    {
        return array_map(fn ($t) => static::castIdOrSlug($t), $tags);
    }
}
