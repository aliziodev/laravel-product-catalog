---
name: laravel-product-catalog
license: MIT
description: >
  Complete guide for the `aliziodev/laravel-product-catalog` package — a variant-centric
  Laravel product catalog with pluggable inventory management. Use this skill whenever
  working with this package: installation & setup, creating Products and ProductVariants,
  inventory management (adjust, reserve, release), taxonomy (Brand, Category, Tag),
  slug routing, search setup (database driver, ScoutSearchDriver, ProductSearchBuilder),
  custom inventory drivers, API Resources, or any question about the
  laravel-product-catalog package. Also trigger when user asks about: ProductCatalog
  facade, InventoryPolicy, inStock scope, buildVariantSku, priceRange, displayName,
  InventoryProviderInterface, ProductSearchBuilder, ScoutSearchDriver,
  `product-catalog.model`, or catalog:install artisan command.
---

# laravel-product-catalog — Skill Guide

`aliziodev/laravel-product-catalog` is a **variant-centric product catalog** for Laravel 12+.
Core philosophy: `Product` is a presentation entity, `ProductVariant` is the sellable unit.
Inventory is pluggable — swap drivers without changing application code.

---

## Installation & Setup

```bash
composer require aliziodev/laravel-product-catalog
php artisan catalog:install --migrate   # publishes config + migrations; --migrate also runs migrate
```

`catalog:install` is not interactive. Without `--migrate` it only publishes, and you run
`php artisan migrate` yourself.

Or manually:
```bash
php artisan vendor:publish --tag=product-catalog-migrations
php artisan migrate
php artisan vendor:publish --tag=product-catalog-config  # optional
```

**Key config** (`config/product-catalog.php`):
```php
'model'       => \App\Models\Product::class, // override when extending the base Product model
'table_prefix' => env('PRODUCT_CATALOG_TABLE_PREFIX', 'catalog_'),  // set BEFORE migrate
'inventory' => [
    'driver'           => env('PRODUCT_CATALOG_INVENTORY_DRIVER', 'database'), // 'database' | 'null' | custom
    'movement_reasons' => [/* ... */],  // reference list only — never read or validated by the package
],
'slug' => [
    'auto_generate'    => true,  // updates only: re-slug when name changes; create always generates slug + route_key
    'route_key_length' => 8,   // random suffix length (4–32)
],
'search' => [
    'driver' => env('PRODUCT_CATALOG_SEARCH_DRIVER', 'database'), // 'database' | 'scout' | custom
],
'routes' => [
    'enabled'     => env('PRODUCT_CATALOG_ROUTES_ENABLED', false),
    'prefix'      => 'catalog',
    'middleware'  => ['api'],
    'expose_meta' => env('PRODUCT_CATALOG_ROUTES_EXPOSE_META', false), // include `meta` in API resources
],
```

> ⚠️ **Pitfall:** Change `table_prefix` **before** running `migrate`. Changing it afterward orphans the old tables — manual rename required.
> Keep it at **17 characters or fewer** on MySQL/MariaDB (64-character identifier limit): the
> longest generated name is `<prefix>inventory_movements_variant_id_created_at_index` (47 + prefix),
> and a longer prefix makes `migrate` fail. PostgreSQL (limit 63) silently truncates instead.
>
> **`movement_reasons` is documentation only.** `$reason` is a free string stored as given; the
> package never checks it against this list. Use `InventoryReason` constants (or your own).
>
> ⚠️ **Scout pitfall:** `ScoutSearchDriver` does **not** use the package base `Product`
> model directly. When enabling Scout, set the top-level `product-catalog.model` to your
> application Product model that extends the package base model and uses both
> `Laravel\Scout\Searchable` and the package `Concerns\Searchable` trait — with an
> `insteadof` block (see ScoutSearchDriver below). This same key is also used by the
> database search driver, the API controller, and the product relations
> (`Brand/Category/Tag::products()`, `ProductVariant/ProductOption::product()`).

---

## Core Models & Namespaces

```php
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Aliziodev\ProductCatalog\Models\InventoryItem;
use Aliziodev\ProductCatalog\Models\Brand;
use Aliziodev\ProductCatalog\Models\Category;
use Aliziodev\ProductCatalog\Models\Tag;
use Aliziodev\ProductCatalog\Enums\ProductType;       // Simple | Variable
use Aliziodev\ProductCatalog\Enums\ProductStatus;     // Draft | Published | Private | Archived
use Aliziodev\ProductCatalog\Enums\InventoryPolicy;   // Track | Allow | Deny
use Aliziodev\ProductCatalog\Enums\InventoryReason;   // preset reason constants for audit trail
use Aliziodev\ProductCatalog\Facades\ProductCatalog;
```

---

## Products

### Creating a Product

```php
// Simple product (single SKU)
$product = Product::create([
    'name'              => 'Wireless Mouse',
    'code'              => 'WM-001',          // parent SKU (optional)
    'type'              => ProductType::Simple,
    'short_description' => 'Ergonomic wireless mouse, 2.4 GHz',
    'meta_title'        => 'Wireless Mouse — Best Price',
    'meta'              => ['warranty' => '1 year'],  // free-form JSON
]);

// Variable product (multiple variants)
$product = Product::create([
    'name' => 'Running Shoes',
    'code' => 'RS-AIR',
    'type' => ProductType::Variable,
]);
```

### Lifecycle

```php
$product->publish();      // draft → published (fires ProductPublished event)
$product->unpublish();    // published → draft
$product->archive();      // → archived (fires ProductArchived event)
$product->makePrivate();  // → private (live but not publicly listed)

// Scheduled publishing — set a future published_at, then publish()
$product->update(['published_at' => now()->addDays(3)]);
$product->publish();      // keeps the existing published_at; ProductPublished fires now, not at go-live

$product->isPublished();  // status only — also true for a scheduled product
$product->isScheduled();  // Published + published_at in the future
$product->isDraft();
$product->isArchived();
$product->isPrivate();
$product->isLive();       // true for Published OR Private

// Status scopes
Product::published()->get();  // public storefront — published_at null or <= now()
Product::scheduled()->get();  // Published with published_at in the future
Product::visible()->get();    // Published + Private (authenticated storefront), same published_at rule
Product::private()->get();    // Private only
```

> A future `published_at` hides a Published product from `published()`, `visible()`, the
> default `ProductSearchBuilder` listing and the API. A null `published_at` counts as live.

---

## Variants & Options

### Variable Product — Options + Variants

```php
// 1. Define option axes
$colorOption = $product->options()->create(['name' => 'Color', 'position' => 1]);
$red  = $colorOption->values()->create(['value' => 'Red',  'position' => 1]);
$blue = $colorOption->values()->create(['value' => 'Blue', 'position' => 2]);

$sizeOption = $product->options()->create(['name' => 'Size', 'position' => 2]);
$s42 = $sizeOption->values()->create(['value' => '42', 'position' => 1]);

// 2. Create variant
$variant = ProductVariant::create([
    'product_id'    => $product->id,
    'sku'           => 'RS-AIR-RED-42',
    'price'         => 850000,
    'compare_price' => 1000000,   // original price (for sale badge)
    'cost_price'    => 500000,    // internal cost
    'weight'        => 0.350,
    'is_default'    => true,
    'is_active'     => true,
    'meta'          => ['barcode' => '8991234567890'],
]);

// 3. Attach option values to variant
$variant->optionValues()->sync([$red->id, $s42->id]);

// 4. Auto-generate SKU (must load optionValues first!)
$variant->load('optionValues');
$sku = $product->buildVariantSku($variant); // "RS-AIR-RED-42"
$variant->update(['sku' => $sku]);
```

### Variant Helpers

```php
$variant->displayName();         // "Red / 42" — human-readable label
$variant->isOnSale();            // true if compare_price > price
$variant->discountPercentage();  // 15 (integer percent)
```

> ⚠️ **Pitfall:** `buildVariantSku()` must be called **after** `$variant->load('optionValues')`. Without it the SKU is wrong.

---

## Inventory

### Setting Up InventoryItem

```php
// ALWAYS create inventoryItem when creating a variant — even for Allow policy
$variant->inventoryItem()->create([
    'quantity'            => 100,
    'policy'              => InventoryPolicy::Track,  // Track | Allow | Deny
    'low_stock_threshold' => 10,
]);
```

**Three policies:**
| Policy | Behaviour |
|--------|-----------|
| `Track` | Checks actual stock; denies when `quantity <= reserved_quantity` |
| `Allow` | Always in stock — overselling permitted (digital, pre-order) |
| `Deny`  | Always out of stock — variant unavailable; `reserve()` throws `InventoryException::purchaseNotAllowed()` |

> ⚠️ **Critical pitfall:** `Product::inStock()` uses `whereHas('inventoryItem', ...)`.
> Variants **without** an `inventoryItem` row are **excluded** from this scope,
> even if the intent is Allow policy. Always create `inventoryItem`!

### Inventory Operations via Facade

```php
$inventory = ProductCatalog::inventory(); // resolves the active driver

// Read
$inventory->getQuantity($variant);         // available qty (total − reserved)
$inventory->isInStock($variant);           // bool
$inventory->canFulfill($variant, 10);      // bool

// Write (all run inside DB::transaction + lockForUpdate — race-condition safe)
$inventory->set($variant, 50, InventoryReason::STOCKTAKE);              // absolute, unrestricted (counts, corrections)
$inventory->adjust($variant, -2, InventoryReason::DAMAGE);             // − deduct available stock only / + restock
$inventory->reserve($variant, 5, InventoryReason::ORDER_PLACED, $order);   // soft-hold; throws for Deny
$inventory->release($variant, 5, InventoryReason::ORDER_CANCELLED, $order); // undo reserve
$inventory->commit($variant, 5, InventoryReason::ORDER_FULFILLED, $order);  // reserve → deduct
```

> ⚠️ **`adjust()` cannot consume reserved stock.** A negative `adjust()` throws
> `InventoryException::insufficientStock()` when the new quantity would drop below
> `reserved_quantity`. Ship reserved units with `commit()`; use `adjust()` for unreserved
> movements (damage, walk-in sale, restock, return) and `set()` for stocktakes.

### Reservation Lifecycle

```
reserve()  →  release()   (order cancelled / cart expired)
reserve()  →  commit()    (order fulfilled — quantity deducted permanently)
```

| Operation | quantity | reserved_quantity | available |
|---|---|---|---|
| `reserve(5)` | unchanged | +5 | −5 |
| `release(5)` | unchanged | −5 | +5 |
| `commit(5)` | −5 | −5 | unchanged |
| `adjust(-5)` | −5 | unchanged | −5 (only if ≥ 5 available, else throws) |

### InventoryItem Helpers (read-only)

```php
$item = $variant->inventoryItem;

$item->availableQuantity();   // quantity - reserved_quantity
$item->isLowStock();          // availableQuantity <= low_stock_threshold
```

### Built-in Inventory Drivers

| Driver | When to use |
|--------|-------------|
| `database` (default) | Stock tracked in DB with pessimistic locking — safe under concurrent requests |
| `null` | Always in stock, no DB writes — for unlimited/digital across the whole app |

> **null driver vs InventoryPolicy::Allow:**
> - `null` driver: **all** variants app-wide are always in stock
> - `InventoryPolicy::Allow`: only the variants you explicitly configure this way are unlimited

---

## Taxonomy

```php
// Brand
$brand = Brand::create(['name' => 'Nike', 'slug' => 'nike']);
$product->update(['brand_id' => $brand->id]);

// Category (supports parent–child hierarchy)
$apparel = Category::create(['name' => 'Apparel', 'slug' => 'apparel']);
$shoes   = Category::create(['name' => 'Shoes', 'slug' => 'shoes', 'parent_id' => $apparel->id]);
$product->update(['primary_category_id' => $shoes->id]);
$product->categories()->sync([$apparel->id, $shoes->id]); // multiple categories

// Category tree
$tree = Category::whereNull('parent_id')->with('children')->orderBy('position')->get();

// Tag
$tag = Tag::create(['name' => 'new-arrival', 'slug' => 'new-arrival']);
$product->tags()->attach($tag);
```

---

## Querying

```php
// Status scopes
Product::published()->get();   // live now (hides scheduled products)
Product::draft()->get();

// Stock
Product::inStock()->get();          // has at least one purchasable active variant

// Local Eloquent scope search (name, code, description, SKU)
Product::search('RS-AIR')->get();

// Filters
Product::forBrand($brand)->published()->get();
Product::withTag($tag)->inStock()->get();

// Price range from active variants
$product->priceRange();  // ['min' => 850000.0, 'max' => 1200000.0] | null
$product->minPrice();
$product->maxPrice();

// Low stock alert
InventoryItem::lowStock()->with('variant.product')->get();
```

### ProductSearchBuilder

```php
use Aliziodev\ProductCatalog\Search\ProductSearchBuilder;

ProductSearchBuilder::query('kemeja')
    ->inCategory('t-shirts')
    ->withTags(['sale', 'new-arrival'])
    ->forBrand('stylehouse')
    ->priceBetween(50_000, 500_000)
    ->onlyInStock()
    ->withStatus('published')   // the default; uses published(), so scheduled products are hidden
    ->sortBy('price')
    ->sortAscending()
    ->paginate(24);
```

### ScoutSearchDriver

```php
// config/product-catalog.php
'model' => \App\Models\Product::class,  // top-level — used by all subsystems
'search' => [
    'driver' => env('PRODUCT_CATALOG_SEARCH_DRIVER', 'database'),
],
```

```php
// app/Models/Product.php
use Aliziodev\ProductCatalog\Concerns\Searchable;
use Aliziodev\ProductCatalog\Models\Product as BaseProduct;
use Laravel\Scout\Searchable as ScoutSearchable;

class Product extends BaseProduct
{
    use ScoutSearchable, Searchable {
        Searchable::toSearchableArray insteadof ScoutSearchable;
        Searchable::makeAllSearchableUsing insteadof ScoutSearchable;
    }

    /** Keep drafts and archived products out of the index. */
    public function shouldBeSearchable(): bool
    {
        return $this->isPublished();
    }
}
```

> ⚠️ **Never write a plain `use ScoutSearchable, Searchable;`** — both traits define
> `toSearchableArray()` and `makeAllSearchableUsing()`, and PHP stops with a fatal
> *"Trait method ... has not been applied ... because of collision"*. `use` order does not
> resolve it; the `insteadof` lines do.
>
> To add index fields, alias the package method — `parent::toSearchableArray()` does not
> exist on the base Product and throws `BadMethodCallException`:
>
> ```php
> use ScoutSearchable, Searchable {
>     Searchable::toSearchableArray insteadof ScoutSearchable;
>     Searchable::makeAllSearchableUsing insteadof ScoutSearchable;
>     Searchable::toSearchableArray as catalogSearchableArray;
> }
>
> public function toSearchableArray(): array
> {
>     return array_merge($this->catalogSearchableArray(), [/* extra fields */]);
> }
> ```
>
> - `shouldBeSearchable()` goes in the app model, not the package trait (adding it to the
>   trait would collide with Scout again). It checks status only, not `published_at` —
>   nothing saves the model when a scheduled time passes, so `ScoutSearchDriver` hides
>   scheduled products at query time through `published()`.
> - With only published products indexed, `ScoutSearchDriver` + `->withStatus('draft')`
>   finds nothing — use the database driver for admin screens.
> - `toSearchableArray()` loads missing `brand`, `primaryCategory`, `categories`, `tags`,
>   `variants` itself, so a single model save indexes the full document;
>   `makeAllSearchableUsing()` eager-loads them for `scout:import`.

```php
use Aliziodev\ProductCatalog\Search\ProductSearchBuilder;
use Aliziodev\ProductCatalog\Search\ScoutSearchDriver;

ProductSearchBuilder::query('kemeja')
    ->usingDriver(app(ScoutSearchDriver::class))
    ->paginate(24);
```

> `ScoutSearchDriver` delegates text search to Laravel Scout, then applies catalog
> filters and optional sort via the Eloquent `query()` callback. Without an explicit
> `sort_by`, Scout engine relevance is preserved.

---

## Slug Routing

Slugs use a **permanent random suffix** — when a product is renamed, the slug prefix changes but the suffix stays the same. Old URLs remain valid.

```
/catalog/wireless-mouse-a1b2c3d4   ← original
/catalog/ergonomic-mouse-a1b2c3d4  ← after rename — same suffix, still resolves
```

```php
$product = Product::findBySlug('ergonomic-mouse-a1b2c3d4');
$product = Product::findBySlugOrFail('ergonomic-mouse-a1b2c3d4');

// Scope
Product::published()->bySlug($slug)->firstOrFail();
```

Enable built-in routes:
```env
PRODUCT_CATALOG_ROUTES_ENABLED=true
# GET /catalog/products
# GET /catalog/products/{slug}
# GET /catalog/categories
# GET /catalog/brands
```

The product endpoints return live products only (`published()`). The `{slug}` endpoint includes
active variants only (`is_active = false` variants are left out).

> ⚠️ The built-in routes have **no names** — `route('catalog.products.index')` does not exist.
> Use `url(config('product-catalog.routes.prefix').'/products')`, or keep `enabled = false`
> and define your own named routes (the package controllers can be reused).

---

## API Resources

```php
use Aliziodev\ProductCatalog\Http\Resources\ProductResource;

$product = Product::with(['brand', 'primaryCategory', 'tags', 'variants'])->findOrFail($id);
return ProductResource::make($product);
```

> `meta` (product and variant) is omitted from `ProductResource` / `ProductVariantResource`
> unless `product-catalog.routes.expose_meta` is `true` (default `false`). When off, the
> `meta` key is absent, not `null`. It defaults off because `meta` often holds internal
> data (supplier codes, `reorder_point`, `lead_time_days`).

Extend to add custom fields:
```php
class CatalogProductResource extends ProductResource
{
    public function toArray($request): array
    {
        return array_merge(parent::toArray($request), [
            'price_range' => $this->resource->priceRange(),
        ]);
    }
}
```

---

## Events

| Event | When | Key payload |
|-------|------|-------------|
| `ProductPublished` | `$product->publish()` | `$event->product` |
| `ProductArchived` | `$product->archive()` | `$event->product` |
| `InventoryAdjusted` | `adjust()`, `set()`, `commit()` | `variant`, `previousQuantity`, `newQuantity`, `reason`, `movement` |
| `InventoryReserved` | `reserve()`, `release()` | `variant`, `type` (MovementType), `quantity`, `reservedBefore`, `reservedAfter`, `movement`; helpers: `isReserve()`, `isRelease()` |
| `InventoryLowStock` | When available crosses `low_stock_threshold` | `variant`, `availableQuantity`, `threshold`, `movement` |
| `InventoryOutOfStock` | When available drops to 0 | `variant`, `movement` |

> `InventoryLowStock` and `InventoryOutOfStock` fire **on crossing** only, not on every subsequent operation below the threshold. `InventoryOutOfStock` takes precedence — both never fire for the same operation.
>
> All six events implement `ShouldDispatchAfterCommit`: inside a DB transaction, listeners
> run after the outermost transaction commits and never if it rolls back; with no open
> transaction they run immediately. Listeners therefore never run inside the order
> transaction or while the inventory row lock is held. `Event::fake()` still records them
> immediately. `ProductPublished` fires on `publish()`, not when a scheduled product goes live.

```php
use Aliziodev\ProductCatalog\Events\ProductPublished;
use Aliziodev\ProductCatalog\Events\InventoryLowStock;
use Aliziodev\ProductCatalog\Events\InventoryOutOfStock;

// Register in EventServiceProvider
ProductPublished::class  => [SendNewProductNotification::class],
InventoryLowStock::class => [NotifyPurchasingTeamListener::class],
InventoryOutOfStock::class => [DisableVariantListener::class],
```

---

## Custom Inventory Driver

If stock is already managed in your own table / ERP / WMS, implement this interface:

```php
use Aliziodev\ProductCatalog\Contracts\InventoryProviderInterface;

class AppInventoryProvider implements InventoryProviderInterface
{
    public function getQuantity(ProductVariant $variant): int { ... }
    public function isInStock(ProductVariant $variant): bool { ... }
    public function canFulfill(ProductVariant $variant, int $quantity): bool { ... }
    public function adjust(ProductVariant $variant, int $delta, string $reason = '', ?Model $reference = null): void { ... }
    public function set(ProductVariant $variant, int $quantity, string $reason = '', ?Model $reference = null): void { ... }
    public function reserve(ProductVariant $variant, int $quantity, string $reason = '', ?Model $reference = null): void { ... }
    public function release(ProductVariant $variant, int $quantity, string $reason = '', ?Model $reference = null): void { ... }
    public function commit(ProductVariant $variant, int $quantity, string $reason = '', ?Model $reference = null): void { ... }
}
```

Register in ServiceProvider:
```php
ProductCatalog::extend('app', fn ($app) => new \App\Inventory\AppInventoryProvider);
```

Activate via `.env`:
```env
PRODUCT_CATALOG_INVENTORY_DRIVER=app
```

Dispatch the package events (`InventoryAdjusted`, `InventoryReserved`, …) from a custom driver
when the app listens for them — they already implement `ShouldDispatchAfterCommit`, so the
same after-commit guarantee holds.

See `references/inventory.md` for full examples (ERP API, fallback strategy).

---

## Common Gotchas

1. **Set `table_prefix` before migrating** — changing it afterward requires manual table renames. Keep it ≤ 17 characters on MySQL/MariaDB.
2. **Always create `inventoryItem`** — variants without one are excluded from `inStock()` scope.
3. **`buildVariantSku()` requires `load('optionValues')`** — call after syncing option values.
4. **Never update `route_key`** — it is the permanent slug identifier. Changing it breaks all existing links.
5. **Soft-deleted Brand/Category** — `$product->brand` returns `null`. Handle gracefully: `$product->brand?->name ?? 'No Brand'`.
6. **Soft-deleted Tag pivot** — the pivot row in `catalog_product_tags` persists after soft delete. Clean up in the `Tag::forceDeleted` event if needed.
7. **`Product::search()` is not the Scout entrypoint by itself** — for Scout integration, use your app Product model with `ScoutSearchable` + the package `Searchable` (resolved with `insteadof`) and set the top-level `product-catalog.model` correctly.
8. **Manual slug override can throw `ProductCatalogException`** — setting `slug` explicitly on `create` or `update` will throw `ProductCatalogException::duplicateSlug()` if the slug is already taken. Auto-generated slugs (no `slug` field set) are always unique and never throw.
9. **`catalog:seed-demo` is idempotent** — safe to run multiple times; uses `firstOrCreate` and skips existing records.
10. **Ship reserved stock with `commit()`, not `adjust()`** — a negative `adjust()` throws when it would dip into `reserved_quantity`.
11. **A future `published_at` hides a Published product** — `published()`, `visible()`, search and the API skip it until then; `isPublished()` still returns `true` (use `isScheduled()`).
12. **`meta` is not in API output by default** — enable `routes.expose_meta` only if nothing internal is stored in `meta`.

---

## Testing Setup

```php
// tests/TestCase.php
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Aliziodev\ProductCatalog\ProductCatalogServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [ProductCatalogServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(
            base_path('vendor/aliziodev/laravel-product-catalog/database/migrations')
        );
    }
}
```

Override the driver in a specific test:
```php
config(['product-catalog.inventory.driver' => 'null']);
```

---

## Query Performance

### N+1 Patterns to Avoid

#### Price helpers (`minPrice` / `maxPrice` / `priceRange`)

Without eager loading, each call hits the DB:

```php
// ❌ N+1 — 2 queries per product (1 for minPrice, 1 for maxPrice)
$products = Product::published()->limit(10)->get();
foreach ($products as $p) {
    $p->minPrice(); // SELECT MIN(price) FROM ...
    $p->maxPrice(); // SELECT MAX(price) FROM ...
}

// ✅ 0 extra queries — in-memory collection is used
$products = Product::published()->with('variants')->limit(10)->get();
foreach ($products as $p) {
    $p->minPrice();   // no DB hit
    $p->maxPrice();   // no DB hit
    $p->priceRange(); // no DB hit
}
```

> The helpers detect `$this->relationLoaded('variants')` and use the
> already-loaded collection when available.

#### Inventory access per variant

```php
// ❌ N+1 — 1 query per variant (30 queries for 10 products × 3 variants)
$products = Product::published()->with('variants')->limit(10)->get();
foreach ($products as $p) {
    foreach ($p->variants as $v) {
        $_ = $v->inventoryItem; // lazy loaded
    }
}

// ✅ 3 queries total — products + variants + inventoryItems (batch)
$products = Product::published()->with('variants.inventoryItem')->limit(10)->get();
foreach ($products as $p) {
    foreach ($p->variants as $v) {
        $_ = $v->inventoryItem; // already in memory — 0 extra queries
    }
}
```

#### Product detail page (show endpoint)

Always eager-load `inventoryItem` on the variants — without it, every `->inventoryItem` access in your view fires a query. The built-in show endpoint also limits variants to active ones:

```php
// ✅ Correct eager-load for a product detail page (same as the built-in show endpoint)
Product::published()
    ->with([
        'brand', 'primaryCategory', 'tags',
        'variants' => fn ($q) => $q->active()->with('inventoryItem'),
        'options.values',
    ])
    ->bySlug($slug)
    ->firstOrFail();
```

### Query Budget Reference

| Operation | Query count | Notes |
|-----------|-------------|-------|
| `Product::published()->with(['brand','primaryCategory','defaultVariant'])->paginate(15)` | ≤ 5 | COUNT + SELECT + 3 eager-load batches |
| `Product::published()->with('variants.inventoryItem')->limit(10)->get()` | 3 | products + variants + inventoryItems |
| `Product::published()->inStock()->paginate(15)` | 2 | COUNT + SELECT (WHERE EXISTS, no JOIN) |
| Product detail page (all relations) | ≤ 8 | products + brand + category + tags + pivot + variants + inventoryItems + options + values |
| `ProductSearchBuilder` with 4 filters + `paginate()` | ≤ 5 | COUNT + SELECT + ≤3 eager-load batches |

### Tag Filter — Single Query

Multi-tag filtering uses a single `IN (subquery)` with `GROUP BY / HAVING` — **not** N correlated `WHERE EXISTS`:

```php
// Both resolve to a single SELECT
Product::withTag($tag->id)->get();                           // single WHERE EXISTS (1 tag)

ProductSearchBuilder::query('')
    ->withTags(['sale', 'new-arrival', 'featured'])
    ->get(); // WHERE id IN (SELECT product_id GROUP BY … HAVING COUNT(DISTINCT tag_id) = 3)
```

### Price Sort — Documented Limitation

`->sortBy('price')` adds a **scalar correlated subquery** in `ORDER BY`:

```sql
ORDER BY (SELECT MIN(price) FROM catalog_product_variants
          WHERE product_id = catalog_products.id AND is_active = 1) ASC
```

This is evaluated once per row in the result set. Acceptable for catalogs up to ~50k products.
For larger catalogs, add a denormalized `min_price` column to `catalog_products` and keep it
synced via a model observer.

### Database Indexes

The package ships these indexes out of the box (names follow `{table}_{column}_index`, so they carry the table prefix):

| Table | Column | Index | Created in | Usage |
|-------|--------|-------|------------|-------|
| `catalog_products` | `name` | `catalog_products_name_index` | `2026_04_01_000004_create_catalog_products_table` | `ORDER BY name`, prefix LIKE searches |
| `catalog_inventory_items` | `policy` | `catalog_inventory_items_policy_index` | `2026_04_01_000011_create_catalog_inventory_items_table` | `inStock()` scope, `lowStock()` scope |

Installs created before v1.5.0 get both from `2026_10_30_000001_sync_catalog_schema_for_upgraded_installs`,
which adds them only when missing. Releases before v1.7.1 named them `idx_catalog_products_name` and
`idx_catalog_inventory_items_policy`, ignoring the prefix; `2026_10_31_000001_prefix_catalog_index_names`
renames them, so two prefixes can share one PostgreSQL or SQLite schema.

---

## Reference Files

Read these when you need deeper coverage on a topic:

- `references/inventory.md` — Full custom driver guide (own table, ERP API, fallback strategy)
- `references/use-cases.md` — Patterns for: Public Catalog, Online Store, Simple Ecommerce, Internal Catalog, Digital+Physical
- `references/api-reference.md` — Complete list of scopes, methods, enums, events, exceptions, DB tables
