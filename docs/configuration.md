# Configuration Reference

Deep dive into every key in `config/product-catalog.php`, including pitfalls and common mistakes.

Publish the config file:

```bash
php artisan vendor:publish --tag=product-catalog-config
```

---

## `model`

```php
'model' => \Aliziodev\ProductCatalog\Models\Product::class,
```

The Eloquent model class used throughout the package. All internal subsystems — the `DatabaseSearchDriver`, `ScoutSearchDriver`, `ProductController`, and the product relations (`Brand::products()`, `Category::products()`, `Tag::products()`, `ProductVariant::product()`, `ProductOption::product()`) — resolve their model through this single key, so `$variant->product` returns your extended model.

Override when you extend the base `Product` model in your application:

```php
// config/product-catalog.php
'model' => \App\Models\Product::class,
```

Your model must extend `Aliziodev\ProductCatalog\Models\Product`. Any class name works (e.g. `CatalogProduct`): the package relations use an explicit `product_id` foreign key instead of deriving one from the class name.

If you are using Scout, add `Laravel\Scout\Searchable` and `Aliziodev\ProductCatalog\Concerns\Searchable` to this application model (not the package base model). Both traits define `toSearchableArray()` and `makeAllSearchableUsing()`, so resolve the collision with `insteadof` — a plain `use ScoutSearchable, Searchable;` is a fatal error:

```php
// app/Models/Product.php
use Aliziodev\ProductCatalog\Models\Product as BaseProduct;
use Aliziodev\ProductCatalog\Concerns\Searchable;
use Laravel\Scout\Searchable as ScoutSearchable;

class Product extends BaseProduct
{
    use ScoutSearchable, Searchable {
        Searchable::toSearchableArray insteadof ScoutSearchable;
        Searchable::makeAllSearchableUsing insteadof ScoutSearchable;
    }
}
```

See [Scout Integration](scout-integration.md) for the full setup.

> **Validated on boot.** `ProductCatalogServiceProvider` checks this key on every request and throws an `InvalidArgumentException` (with a clear message) if the value is empty, the class doesn't exist, or it doesn't extend the base `Product`.

---

## `table_prefix`

```php
'table_prefix' => env('PRODUCT_CATALOG_TABLE_PREFIX', 'catalog_'),
```

Prefix prepended to every table the package creates:

| Value | Tables created |
|---|---|
| `catalog_` (default) | `catalog_products`, `catalog_product_variants`, … |
| `shop_` | `shop_products`, `shop_product_variants`, … |
| `''` (empty) | `products`, `product_variants`, … |

> **Pitfall — Change BEFORE running migrations.**
> If you change this value after `php artisan migrate` has already run, the package will look for tables with the new prefix that don't exist yet. Your old tables remain orphaned under the old prefix. There is no automatic rename.
>
> If you need to change the prefix after migration, rename the tables manually and update this config simultaneously.

> **Pitfall — Keep the prefix at 17 characters or fewer on MySQL/MariaDB.**
> MySQL and MariaDB reject identifiers longer than 64 characters. The longest name Laravel generates for the package is `<prefix>inventory_movements_variant_id_created_at_index` — 47 characters plus the prefix — so a longer prefix makes `migrate` fail. PostgreSQL allows 63 characters (a prefix of up to 16) and silently truncates longer names instead of failing. The default `catalog_` (8 characters) fits both.

---

## `inventory.driver`

```php
'inventory' => [
    'driver' => env('PRODUCT_CATALOG_INVENTORY_DRIVER', 'database'),
],
```

### Built-in drivers

| Driver | Behaviour |
|---|---|
| `database` | Tracks stock in `catalog_inventory_items`. Writes movement history to `catalog_inventory_movements`. Default. |
| `null` | Always returns in stock. `adjust()` and `set()` are no-ops. Use for unlimited-stock catalogs or when stock is managed externally. |

### Custom drivers

Register via `ProductCatalog::extend()` in a `ServiceProvider`. See [Custom Inventory Provider](custom-inventory-provider.md).

> **Pitfall — `null` driver vs. `InventoryPolicy::Allow`.**
> These are different tools:
> - `null` driver: **all** variants are always in stock, no `inventoryItem` records needed.
> - `InventoryPolicy::Allow`: only the variants you explicitly configure this way are unlimited. Other variants on the same product can still be `Track` or `Deny`.
>
> Use `InventoryPolicy::Allow` for per-variant control (e.g. a product with one digital variant and one physical variant). Use the `null` driver only when you want to bypass inventory tracking entirely for the whole application.

> **Pitfall — `scopeInStock()` requires an `inventoryItem` record.**
> `Product::inStock()` uses `whereHas('inventoryItem', ...)` internally. A variant with no `inventoryItem` row is **excluded** from in-stock results regardless of policy.
>
> Always create an `inventoryItem` when creating a variant, even for `Allow` policy:
> ```php
> $variant->inventoryItem()->create([
>     'quantity' => 0,
>     'policy'   => InventoryPolicy::Allow,
> ]);
> ```

---

## `inventory.movement_reasons`

```php
'inventory' => [
    'movement_reasons' => [
        'purchase', 'return', 'sale', 'damage', 'expiry', 'correction', 'stocktake',
        'order_placed', 'cart_hold', 'order_cancelled', 'cart_released', 'timeout',
        'order_fulfilled',
    ],
],
```

A reference list of reason strings that mirrors the `InventoryReason` constants. **It is documentation only — nothing in the package reads it.** The `$reason` passed to `adjust()`, `set()`, `reserve()`, `release()` and `commit()` is a free string, stored on the movement record as given and never checked against this list. Adding a string here does not register or validate it.

Use the `InventoryReason` constants, and your own constants for app-specific reasons. If you want to enforce the list, validate against `config('product-catalog.inventory.movement_reasons')` in your own code before calling the driver.

---

## `slug`

```php
'slug' => [
    'auto_generate'    => true,
    'separator'        => '-',
    'route_key_length' => (int) env('PRODUCT_CATALOG_ROUTE_KEY_LENGTH', 8),
],
```

### `auto_generate`

When `true`, the slug prefix is automatically regenerated when the product `name` changes. The permanent `route_key` suffix is **never** changed after creation, so existing URLs continue to resolve.

Set to `false` if you manage slugs manually:
```php
$product->update(['slug' => 'my-custom-slug-' . $product->route_key]);
```

> **Note:** `auto_generate` only affects updates. On create, `route_key` and `slug` are always generated when you leave them empty, whatever this setting says. With `auto_generate = false`, renaming a product keeps its current slug until you change `slug` yourself.

> **Manual slug uniqueness.** When you explicitly set a `slug` on `create` or `update`, the package validates it for uniqueness **before** hitting the database and throws `ProductCatalogException::duplicateSlug()` with a helpful message instead of a raw `SQLSTATE[23000]`. Auto-generated slugs are always unique via the random `route_key` suffix and never trigger this check.

### `separator`

Character used between words in the generated slug and between the slug prefix and the `route_key` suffix. Default: `-`.

### `route_key_length`

Length of the random alphanumeric suffix appended permanently to the slug. Clamped to **4–32**.

- `8` (default): low collision risk for most catalogs (36^8 ≈ 2.8 trillion combinations)
- `4`: compact URLs, higher collision risk on large catalogs
- `12+`: maximum uniqueness, longer URLs

> **Pitfall — Changing `route_key_length` only affects new products.**
> Existing products keep their current `route_key`. Old and new slugs coexist without conflict.

> **Pitfall — `route_key` is permanent by design.**
> Do not update `route_key` after a product is created. It is the stable identifier behind the slug. Changing it breaks all existing shareable links and bookmarks.

---

## `search.driver`

```php
'search' => [
    'driver' => env('PRODUCT_CATALOG_SEARCH_DRIVER', 'database'),
],
```

The driver used by `ProductSearchBuilder` and `ProductCatalog::search()`.

| Driver | Behaviour |
|---|---|
| `database` | Default. Plain Eloquent queries, no extra dependencies. Text search uses `LIKE`, or `MATCH … AGAINST` when `search.fulltext` is on. |
| `scout` | Text search through Laravel Scout (Algolia, Meilisearch, Typesense or Scout's database engine); catalog filters and sorting still run in Eloquent. Requires `laravel/scout` and an application model in `model` — see [Scout Integration](scout-integration.md). |

Register your own driver with `ProductCatalog::extendSearch('my-driver', fn ($app) => new MyDriver)` and select it with `PRODUCT_CATALOG_SEARCH_DRIVER=my-driver`.

---

## `search.fulltext`

```php
'search' => [
    'fulltext' => env('PRODUCT_CATALOG_SEARCH_FULLTEXT', false),
],
```

Only read by the `database` search driver.

- `false` (default): `LIKE '%term%'` on the product `name`, `code` and `short_description` and the variant `sku`. Works on every database, but a leading wildcard cannot use an index, so large tables are scanned.
- `true`: `MATCH(name, short_description) AGAINST(term)` on the products table. Variant SKUs are still matched with `LIKE` because they live in another table; `code` is not searched in this mode.

> **Pitfall — FULLTEXT needs MySQL/MariaDB and an index you add yourself.**
> The package migrations do not create the FULLTEXT index. Add it before turning the option on:
> ```php
> Schema::table(config('product-catalog.table_prefix', 'catalog_').'products', function (Blueprint $table) {
>     $table->fullText(['name', 'short_description']);
> });
> ```
> SQLite has no FULLTEXT support — keep the option `false` in a SQLite test environment.

---

## `routes`

```php
'routes' => [
    'enabled'     => env('PRODUCT_CATALOG_ROUTES_ENABLED', false),
    'prefix'      => env('PRODUCT_CATALOG_ROUTES_PREFIX', 'catalog'),
    'middleware'  => ['api'],
    'expose_meta' => env('PRODUCT_CATALOG_ROUTES_EXPOSE_META', false),
],
```

### `enabled`

Set to `true` to register the built-in read-only catalog API:

```
GET /catalog/products           → ProductController@index
GET /catalog/products/{slug}    → ProductController@show
GET /catalog/categories         → CategoryController@index
GET /catalog/brands             → BrandController@index
```

The product endpoints return only live products (`Product::published()`), so products scheduled for a future `published_at` stay hidden. `show` includes active variants only — a variant with `is_active = false` is left out. `categories` returns the root categories with their children, ordered by `position`; `brands` returns all brands ordered by name.

The routes have no names — see the note at the end of this section.

Leave `false` (default) if you are building your own controllers — the package models and resources are still fully available.

### `prefix`

URL prefix for the catalog routes. Change to avoid conflicts with your application's existing routes:

```env
PRODUCT_CATALOG_ROUTES_PREFIX=api/v1
```

Results in:
```
GET /api/v1/products
GET /api/v1/products/{slug}
GET /api/v1/categories
GET /api/v1/brands
```

### `middleware`

Array of middleware applied to all catalog routes. Default is `['api']`. Add auth middleware to protect routes:

```php
'middleware' => ['api', 'auth:sanctum'],
```

### `expose_meta`

When `true`, `ProductResource` and `ProductVariantResource` include the product and variant `meta` JSON. Default `false`: the `meta` key is left out of the response entirely (it is not `null`).

```env
PRODUCT_CATALOG_ROUTES_EXPOSE_META=true
```

It is off by default because `meta` often holds internal data — the [Internal Catalog](internal-catalog.md) guide stores `reorder_point` and `lead_time_days` there. The setting applies wherever the two resources are used, including your own controllers. Turn it on only when everything you keep in `meta` is safe to publish.

> **Note — the built-in routes are unnamed.**
> There is no `catalog.products.index` or similar, so `route(...)` cannot point at them. Build the URL from the prefix — `url(config('product-catalog.routes.prefix').'/products')` — or keep `enabled = false` and register your own named routes, reusing the package controllers if they fit:
> ```php
> use Aliziodev\ProductCatalog\Http\Controllers\ProductController;
>
> Route::get('catalog/products', [ProductController::class, 'index'])->name('catalog.products.index');
> Route::get('catalog/products/{slug}', [ProductController::class, 'show'])->name('catalog.products.show');
> ```

---

## Common Gotchas

### Soft-deleted Tag not removed from product pivot

When a `Tag` is soft deleted (`$tag->delete()`), the pivot row in `catalog_product_tags` remains. Queries through `$product->tags()` will correctly exclude the soft-deleted tag because Eloquent applies the `SoftDeletes` global scope. However, raw pivot queries (via `DB::table()`) will still see the row.

If you need to clean up stale pivot rows after a tag is permanently deleted, listen to the `Tag::forceDeleted` event:

```php
Tag::forceDeleted(function (Tag $tag) {
    DB::table(config('product-catalog.table_prefix', 'catalog_') . 'product_tags')
        ->where('tag_id', $tag->id)
        ->delete();
});
```

### `buildVariantSku()` must be called after loading `optionValues`

```php
// ❌ Will produce wrong result — optionValues not loaded
$sku = $product->buildVariantSku($variant);

// ✓ Correct
$variant->optionValues()->sync($valueIds);
$variant->load('optionValues');
$sku = $product->buildVariantSku($variant);
$variant->update(['sku' => $sku]);
```

### Soft-deleted Brand or Category still referenced by products

When a `Brand` or `Category` is soft deleted, products that reference it via `brand_id` or `primary_category_id` retain the FK value. The relationship query (`$product->brand`) will return `null` because the brand is soft deleted.

Handle this gracefully in your views:

```php
$brandName = $product->brand?->name ?? 'No Brand';
$categoryName = $product->primaryCategory?->name ?? 'Uncategorized';
```

To find all products referencing a soft-deleted brand before permanently deleting it:

```php
$brand->delete(); // soft delete

// Products still reference brand_id — brand relationship returns null
// Before forceDelete, reassign or notify:
$orphaned = Product::where('brand_id', $brand->id)->count();
```

### Changing `table_prefix` after migration

See the `table_prefix` section above. Summary: change prefix only **before** running `php artisan migrate` for the first time.
