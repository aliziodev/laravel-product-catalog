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
php artisan catalog:install   # interactive — publishes config + runs migrations
```

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
    'movement_reasons' => [],  // add app-specific reason strings here
],
'slug' => [
    'auto_generate'    => true,
    'route_key_length' => 8,   // random suffix length (4–32)
],
'search' => [
    'driver' => env('PRODUCT_CATALOG_SEARCH_DRIVER', 'database'), // 'database' | 'scout' | custom
],
'routes' => [
    'enabled'    => env('PRODUCT_CATALOG_ROUTES_ENABLED', false),
    'prefix'     => 'catalog',
    'middleware' => ['api'],
],
```

> ⚠️ **Pitfall:** Change `table_prefix` **before** running `migrate`. Changing it afterward orphans the old tables — manual rename required.
>
> ⚠️ **Scout pitfall:** `ScoutSearchDriver` does **not** use the package base `Product`
> model directly. When enabling Scout, set the top-level `product-catalog.model` to your
> application Product model that extends the package base model and uses both
> `Laravel\Scout\Searchable` and the package `Concerns\Searchable` trait. This same key
> is also used by the database search driver and the API controller.

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

$product->isPublished();
$product->isDraft();
$product->isArchived();
$product->isPrivate();
$product->isLive();       // true for Published OR Private

// Status scopes
Product::published()->get();  // public storefront
Product::visible()->get();    // Published + Private (authenticated storefront)
Product::private()->get();    // Private only
```

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
| `Deny`  | Always out of stock — variant unavailable |

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
$inventory->set($variant, 50, InventoryReason::STOCKTAKE);
$inventory->adjust($variant, -5, InventoryReason::SALE, $order);    // − deduct / + restock
$inventory->reserve($variant, 5, InventoryReason::ORDER_PLACED, $order);   // soft-hold
$inventory->release($variant, 5, InventoryReason::ORDER_CANCELLED, $order); // undo reserve
$inventory->commit($variant, 5, InventoryReason::ORDER_FULFILLED, $order);  // reserve → deduct
```

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
| `adjust(-5)` | −5 | unchanged | −5 |

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
Product::published()->get();
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
    ->withStatus('published')
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
    use ScoutSearchable, Searchable;
}
```

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
```

---

## API Resources

```php
use Aliziodev\ProductCatalog\Http\Resources\ProductResource;

$product = Product::with(['brand', 'primaryCategory', 'tags', 'variants'])->findOrFail($id);
return ProductResource::make($product);
```

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

See `references/inventory.md` for full examples (ERP API, fallback strategy).

---

## Common Gotchas

1. **Set `table_prefix` before migrating** — changing it afterward requires manual table renames.
2. **Always create `inventoryItem`** — variants without one are excluded from `inStock()` scope.
3. **`buildVariantSku()` requires `load('optionValues')`** — call after syncing option values.
4. **Never update `route_key`** — it is the permanent slug identifier. Changing it breaks all existing links.
5. **Soft-deleted Brand/Category** — `$product->brand` returns `null`. Handle gracefully: `$product->brand?->name ?? 'No Brand'`.
6. **Soft-deleted Tag pivot** — the pivot row in `catalog_product_tags` persists after soft delete. Clean up in the `Tag::forceDeleted` event if needed.
7. **`Product::search()` is not the Scout entrypoint by itself** — for Scout integration, use your app Product model with `ScoutSearchable` and set the top-level `product-catalog.model` correctly.
8. **Manual slug override can throw `ProductCatalogException`** — setting `slug` explicitly on `create` or `update` will throw `ProductCatalogException::duplicateSlug()` if the slug is already taken. Auto-generated slugs (no `slug` field set) are always unique and never throw.
9. **`catalog:seed-demo` is idempotent** — safe to run multiple times; uses `firstOrCreate` and skips existing records.

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

Always include `variants.inventoryItem` — without it, every `->inventoryItem` access in your view fires a query:

```php
// ✅ Correct eager-load for a product detail page
Product::published()
    ->with(['brand', 'primaryCategory', 'tags', 'variants.inventoryItem', 'options.values'])
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

The package ships these indexes out of the box (migration `2026_04_01_000013`):

| Table | Column | Index | Usage |
|-------|--------|-------|-------|
| `catalog_products` | `name` | `idx_catalog_products_name` | `ORDER BY name`, prefix LIKE searches |
| `catalog_inventory_items` | `policy` | `idx_catalog_inventory_items_policy` | `inStock()` scope, `lowStock()` scope |

---

## Reference Files

Read these when you need deeper coverage on a topic:

- `references/inventory.md` — Full custom driver guide (own table, ERP API, fallback strategy)
- `references/use-cases.md` — Patterns for: Public Catalog, Online Store, Simple Ecommerce, Internal Catalog, Digital+Physical
- `references/api-reference.md` — Complete list of scopes, methods, enums, events, exceptions, DB tables
