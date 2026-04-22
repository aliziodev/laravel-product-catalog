# Scout Integration

This guide covers integrating **Laravel Scout** with `aliziodev/laravel-product-catalog` to power full-text search via Meilisearch, Algolia, Typesense, or the Scout database engine.

---

## When to Use Scout vs the Database Driver

| | `database` driver | `scout` driver |
|---|---|---|
| **Setup** | Zero — works out of the box | Requires laravel/scout + engine config |
| **Relevance ranking** | None (LIKE order) | Engine-native (typo tolerance, boosting) |
| **Typo tolerance** | ❌ | ✅ (Meilisearch, Algolia, Typesense) |
| **Filters / facets** | Eloquent (all filters) | Engine + Eloquent hybrid |
| **Pagination accuracy** | ✅ Exact | ⚠️ Based on engine results (see caveat) |
| **Best for** | Catalogs up to ~10k products | Larger catalogs or search-first UX |

Use the `database` driver to get started. Switch to `scout` when you need relevance ranking, typo tolerance, or instant search.

---

## Installation

### 1. Install Laravel Scout

```bash
composer require laravel/scout
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```

### 2. Install an Engine Driver

**Meilisearch** (recommended for self-hosted):
```bash
composer require meilisearch/meilisearch-php http-interop/http-factory-guzzle
```

**Algolia** (recommended for managed SaaS):
```bash
composer require algolia/algoliasearch-client-php
```

**Typesense**:
```bash
composer require typesense/typesense-php
```

**Database engine** (for teams not ready to run a search server):
```bash
# No extra package needed — included in laravel/scout
```

### 3. Configure Your Engine

```env
# .env — Meilisearch example
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=your-meilisearch-master-key

# Algolia example
# SCOUT_DRIVER=algolia
# ALGOLIA_APP_ID=your-app-id
# ALGOLIA_SECRET=your-admin-key

# Database engine (development / low-traffic)
# SCOUT_DRIVER=database
```

---

## Extending Product with Searchable

Because the package's `Product` model cannot hold a Scout dependency directly, you extend it in your application.

```php
// app/Models/Product.php
<?php

namespace App\Models;

use Aliziodev\ProductCatalog\Concerns\Searchable;
use Aliziodev\ProductCatalog\Models\Product as BaseProduct;
use Laravel\Scout\Searchable as ScoutSearchable;

class Product extends BaseProduct
{
    // ScoutSearchable provides the Scout indexing infrastructure.
    // Searchable (package concern) provides toSearchableArray() + makeAllSearchableUsing().
    use ScoutSearchable, Searchable;
}
```

> **Trait conflict:** Both traits define `toSearchableArray()`. PHP resolves it in favour of
> the last `use` statement. Since `Searchable` is listed after `ScoutSearchable`, the
> package's `toSearchableArray()` wins — which is what you want.

### What Gets Indexed

The package's `Concerns\Searchable::toSearchableArray()` indexes:

| Field | Description |
|---|---|
| `id`, `name`, `code`, `slug` | Core identity fields |
| `short_description`, `description` | Searchable text |
| `type`, `status` | Filterable attributes |
| `brand_name` | Searchable + filterable |
| `primary_category_name`, `primary_category_slug` | Primary category (facet-ready) |
| `categories[]`, `category_slugs[]` | All assigned categories |
| `tags[]` | Tag slugs (facet-ready) |
| `skus[]` | All variant SKUs |
| `min_price` | Lowest active variant price (sortable) |
| `published_at` | Sortable date |

### Customising the Index

Override `toSearchableArray()` in your `App\Models\Product` if you need to add or remove fields:

```php
public function toSearchableArray(): array
{
    $base = parent::toSearchableArray(); // package defaults

    return array_merge($base, [
        'custom_field' => $this->meta['custom'] ?? null,
    ]);
}
```

---

## Importing Existing Products

Index all existing products into the search engine:

```bash
php artisan scout:import "App\Models\Product"
```

The `makeAllSearchableUsing()` method (provided by the package's `Searchable` concern) eager-loads `brand`, `primaryCategory`, `categories`, `tags`, and `variants` during the import — no N+1 queries.

---

## Switching to ScoutSearchDriver

In your `.env`:

```env
PRODUCT_CATALOG_SEARCH_DRIVER=scout
```

In `config/product-catalog.php`, set the top-level `model` key to your application's extended Product model:

```php
use App\Models\Product;

'model' => Product::class,
```

This single key is read by **all** package subsystems — the database search driver, the Scout search driver, and the API controller — so you only configure your extended model once. `ScoutSearchDriver` additionally expects the configured model to use both `Laravel\Scout\Searchable` and the package's `Concerns\Searchable` trait.

Or per-request using `usingDriver()`:

```php
use Aliziodev\ProductCatalog\Search\ProductSearchBuilder;
use Aliziodev\ProductCatalog\Search\ScoutSearchDriver;

ProductSearchBuilder::query('kemeja')
    ->usingDriver(app(ScoutSearchDriver::class))
    ->paginate(24);
```

### How Filtering Works with Scout

`ScoutSearchDriver` uses a hybrid approach:

1. **Text search** — delegated to the Scout engine (Meilisearch, Algolia, etc.)
2. **Catalog filters** — applied via an Eloquent `->query()` callback on Scout results

```php
// Under the hood — simplified
App\Models\Product::search('kemeja')
    ->query(function ($eloquentBuilder) {
        // Category, brand, tags, price range, in_stock, type, status filters
        // applied here via Eloquent
    })
    ->paginate(24);
```

### ⚠️ Pagination Caveat

Scout paginates over the **raw engine result count**, not the post-Eloquent-filter count. If your Eloquent filters remove results after Scout retrieves them, the paginator's `total()` may be higher than the actual visible rows.

**Mitigation:** Push filters into the engine index as filterable attributes (see engine setup below) so the engine itself does the filtering — not Eloquent as a post-process. This gives accurate counts.

---

## Engine-Specific Setup

### Meilisearch

After importing, configure filterable and sortable attributes so the engine can filter efficiently:

```php
// In a ServiceProvider or Artisan command
use Meilisearch\Client;

$client = new Client(config('scout.meilisearch.host'), config('scout.meilisearch.key'));

$client->index('products')->updateFilterableAttributes([
    'status',
    'type',
    'brand_name',
    'primary_category_slug',
    'category_slugs',
    'tags',
    'min_price',
]);

$client->index('products')->updateSortableAttributes([
    'name',
    'min_price',
    'published_at',
]);
```

Or in `config/scout.php`:

```php
'meilisearch' => [
    'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
    'key'  => env('MEILISEARCH_KEY'),
    'index-settings' => [
        \App\Models\Product::class => [
            'filterableAttributes' => [
                'status', 'type', 'brand_name',
                'primary_category_slug', 'category_slugs', 'tags',
            ],
            'sortableAttributes' => ['name', 'min_price', 'published_at'],
        ],
    ],
],
```

Then sync the settings:

```bash
php artisan scout:sync-index-settings
```

### Algolia

Configure searchable and faceting attributes in `config/scout.php`:

```php
'algolia' => [
    // index settings are managed via the Algolia dashboard or the Scout Algolia driver
],
```

Or directly via the Algolia API in a ServiceProvider:

```php
use Algolia\AlgoliaSearch\SearchClient;

$client = SearchClient::create(
    config('scout.algolia.id'),
    config('scout.algolia.secret')
);

$client->initIndex('products')->setSettings([
    'searchableAttributes' => ['name', 'code', 'short_description', 'brand_name', 'skus'],
    'attributesForFaceting' => [
        'filterOnly(status)', 'filterOnly(type)',
        'brand_name', 'primary_category_slug', 'category_slugs', 'tags',
    ],
    'customRanking' => ['desc(published_at)'],
]);
```

### Typesense

```php
// config/scout.php
'typesense' => [
    'client-settings' => [
        'api_key' => env('TYPESENSE_API_KEY', 'xyz'),
        'nodes'   => [
            ['host' => env('TYPESENSE_HOST', 'localhost'), 'port' => 8108, 'protocol' => 'http'],
        ],
    ],
    'model-settings' => [
        \App\Models\Product::class => [
            'collection-schema' => [
                'fields' => [
                    ['name' => 'id',                    'type' => 'string'],
                    ['name' => 'name',                  'type' => 'string'],
                    ['name' => 'short_description',     'type' => 'string', 'optional' => true],
                    ['name' => 'brand_name',            'type' => 'string', 'optional' => true, 'facet' => true],
                    ['name' => 'primary_category_slug', 'type' => 'string', 'optional' => true, 'facet' => true],
                    ['name' => 'category_slugs',        'type' => 'string[]', 'optional' => true, 'facet' => true],
                    ['name' => 'tags',                  'type' => 'string[]', 'optional' => true, 'facet' => true],
                    ['name' => 'min_price',             'type' => 'float',  'optional' => true],
                    ['name' => 'published_at',          'type' => 'string', 'optional' => true],
                ],
                'default_sorting_field' => '',
            ],
        ],
    ],
],
```

---

## Custom Search Driver

If Scout doesn't fit your stack, implement `SearchDriverInterface` directly:

```php
<?php

namespace App\Search;

use Aliziodev\ProductCatalog\Contracts\SearchDriverInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MySearchDriver implements SearchDriverInterface
{
    public function paginate(string $query, array $filters, int $perPage, int $page): LengthAwarePaginator
    {
        // Delegate to your own search engine
    }

    public function get(string $query, array $filters): Collection
    {
        // ...
    }
}
```

Register in a `ServiceProvider`:

```php
use Aliziodev\ProductCatalog\Facades\ProductCatalog;

public function boot(): void
{
    ProductCatalog::extendSearch('my-engine', fn ($app) => new \App\Search\MySearchDriver);
}
```

```env
PRODUCT_CATALOG_SEARCH_DRIVER=my-engine
```

---

## Keeping the Index in Sync

Scout automatically syncs the index when a model is created, updated, or deleted — as long as the model uses `ScoutSearchable`. No extra configuration needed.

For bulk re-indexing after a schema or `toSearchableArray()` change:

```bash
php artisan scout:flush "App\Models\Product"
php artisan scout:import "App\Models\Product"
```

---

## Removing from Index

Soft-deleted products are automatically removed from the Scout index when `SoftDeletes` is used alongside `ScoutSearchable`. Scout respects the `usesSoftDelete()` method and calls `unsearchable()` on soft-delete.

To manually remove a product:

```php
$product->unsearchable();
```

To re-index after restoring:

```php
$product->restore();
$product->searchable();
```

---

## References

- [Laravel Scout — GitHub](https://github.com/laravel/scout)
