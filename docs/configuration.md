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

The Eloquent model class used throughout the package. All internal subsystems — the `DatabaseSearchDriver`, `ScoutSearchDriver`, and `ProductController` — resolve their model through this single key.

Override when you extend the base `Product` model in your application:

```php
// config/product-catalog.php
'model' => \App\Models\Product::class,
```

Your model must extend `Aliziodev\ProductCatalog\Models\Product`. If you are using Scout, add `Laravel\Scout\Searchable` and `Aliziodev\ProductCatalog\Concerns\Searchable` to this application model (not the package base model):

```php
// app/Models/Product.php
use Aliziodev\ProductCatalog\Models\Product as BaseProduct;
use Aliziodev\ProductCatalog\Concerns\Searchable;
use Laravel\Scout\Searchable as ScoutSearchable;

class Product extends BaseProduct
{
    use ScoutSearchable, Searchable;
}
```

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

> **Note:** Even with `auto_generate = false`, you must set `slug` and `route_key` yourself on first create, or slug generation is skipped entirely.

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

## `routes`

```php
'routes' => [
    'enabled'    => env('PRODUCT_CATALOG_ROUTES_ENABLED', false),
    'prefix'     => env('PRODUCT_CATALOG_ROUTES_PREFIX', 'catalog'),
    'middleware' => ['api'],
],
```

### `enabled`

Set to `true` to register the built-in read-only catalog API:

```
GET /catalog/products           → ProductController@index
GET /catalog/products/{slug}    → ProductController@show
```

Leave `false` (default) if you are building your own controllers — the package models and resources are still fully available.

### `prefix`

URL prefix for the catalog routes. Change to avoid conflicts with your application's existing routes:

```env
PRODUCT_CATALOG_ROUTES_PREFIX=api/v1/products
```

Results in:
```
GET /api/v1/products
GET /api/v1/products/{slug}
```

### `middleware`

Array of middleware applied to all catalog routes. Default is `['api']`. Add auth middleware to protect routes:

```php
'middleware' => ['api', 'auth:sanctum'],
```

> **Pitfall — `enabled = true` with conflicting route names.**
> The built-in routes are named `catalog.products.index` and `catalog.products.show`. If your application already has routes with these names, there will be a conflict. Either keep `enabled = false` and define your own routes, or rename yours.

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
