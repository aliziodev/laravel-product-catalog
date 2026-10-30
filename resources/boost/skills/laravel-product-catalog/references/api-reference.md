# API Reference — laravel-product-catalog

Quick lookup for all scopes, methods, enums, events, exceptions, and DB tables.

---

## Product — Scopes

| Scope | Description |
|-------|-------------|
| `Product::published()` | Status = published AND (`published_at` null OR <= now()) — hides scheduled products |
| `Product::scheduled()` | Status = published AND `published_at` > now() |
| `Product::draft()` | Status = draft |
| `Product::archived()` | Status = archived |
| `Product::private()` | Status = private |
| `Product::visible()` | Status = published OR private (authenticated storefronts), same `published_at` rule as `published()` |
| `Product::inStock()` | Has at least one purchasable active variant (requires inventoryItem row!) |
| `Product::search($term)` | Local Eloquent scope search: name, code, short_description, variant SKUs |
| `Product::forBrand($brand)` | Filter by brand model instance |
| `Product::withTag($tag)` | Filter by tag model instance |
| `Product::bySlug($slug)` | Filter by slug (matches on the permanent route_key suffix) |

## Product — Methods

| Method | Return | Description |
|--------|--------|-------------|
| `$product->publish()` | void | draft → published; sets `published_at` to now unless already set (a future value schedules it); fires `ProductPublished` immediately |
| `$product->unpublish()` | void | published → draft |
| `$product->archive()` | void | → archived; fires `ProductArchived` |
| `$product->makePrivate()` | void | → private (live but not publicly listed) |
| `$product->isPublished()` | bool | Status only — also true for a scheduled product |
| `$product->isScheduled()` | bool | Published AND `published_at` in the future |
| `$product->isDraft()` | bool | |
| `$product->isArchived()` | bool | |
| `$product->isPrivate()` | bool | |
| `$product->isLive()` | bool | true for Published OR Private |
| `$product->isSimple()` | bool | type = Simple |
| `$product->isVariable()` | bool | type = Variable |
| `$product->priceRange()` | `['min'=>float,'max'=>float]\|null` | Price range from active variants |
| `$product->minPrice()` | `float\|null` | Lowest active variant price |
| `$product->maxPrice()` | `float\|null` | Highest active variant price |
| `$product->buildVariantSku($variant)` | string | Generate SKU from code + option values. Requires `$variant->load('optionValues')` first |
| `Product::findBySlug($slug)` | `?Product` | Static finder by slug |
| `Product::findBySlugOrFail($slug)` | `Product` | Or throws ModelNotFoundException |

## Product — Relationships

```php
$product->variants()          // HasMany ProductVariant
$product->options()           // HasMany ProductOption
$product->brand()             // BelongsTo Brand
$product->primaryCategory()   // BelongsTo Category
$product->categories()        // BelongsToMany Category
$product->tags()              // BelongsToMany Tag
```

`variants()`, `defaultVariant()` and `options()` use an explicit `product_id` foreign key, so an
extended model with any class name (e.g. `CatalogProduct`) works. The reverse relations —
`$brand->products()`, `$category->products()`, `$tag->products()`, `$variant->product()`,
`$option->product()` — return the configured `product-catalog.model` class.

---

## ProductVariant — Methods

| Method | Return | Description |
|--------|--------|-------------|
| `$variant->displayName()` | string | "Red / 42" — combined option values label |
| `$variant->isOnSale()` | bool | compare_price > price |
| `$variant->discountPercentage()` | int | Discount percentage (integer) |

## ProductVariant — Relationships

```php
$variant->product()           // BelongsTo the configured product-catalog.model
$variant->inventoryItem()     // HasOne InventoryItem
$variant->optionValues()      // BelongsToMany ProductOptionValue
$variant->movements()         // HasMany InventoryMovement
```

---

## InventoryItem — Methods

| Method | Return | Description |
|--------|--------|-------------|
| `$item->availableQuantity()` | int | `quantity - reserved_quantity` |
| `$item->reserve($qty)` | void | Increment reserved_quantity |
| `$item->release($qty)` | void | Decrement reserved_quantity |
| `$item->isLowStock()` | bool | `availableQuantity() <= low_stock_threshold` |
| `InventoryItem::lowStock()` | scope | Filter items with low stock |

---

## ProductCatalog Facade — Inventory Driver

```php
$inv = ProductCatalog::inventory();  // resolves the active driver

// Read
$inv->getQuantity($variant)                                     // int — available (total − reserved)
$inv->isInStock($variant)                                       // bool
$inv->canFulfill($variant, $qty)                                // bool

// Write — all run inside DB::transaction + lockForUpdate (race-condition safe)
$inv->adjust($variant, $delta, $reason, $reference)             // void — + restock, − deduct (available stock only)
$inv->set($variant, $qty, $reason, $reference)                  // void — absolute quantity, unrestricted
$inv->reserve($variant, $qty, $reason, $reference)              // void — soft-hold (reserved_quantity++); throws for Deny
$inv->release($variant, $qty, $reason, $reference)              // void — undo reserve (reserved_quantity--)
$inv->commit($variant, $qty, $reason, $reference)               // void — reservation → permanent deduction

ProductCatalog::extend($name, $resolver)  // register a custom driver
```

Built-in `database` driver rules:
- `adjust()` with a negative delta throws `InventoryException::insufficientStock()` when the new quantity would drop below `reserved_quantity`. Ship reserved units with `commit()`; use `adjust()` for unreserved movements (damage, walk-in sale, restock, return) and `set()` for stocktakes. Positive deltas are always allowed.
- `reserve()` throws `InventoryException::purchaseNotAllowed()` for `InventoryPolicy::Deny`; it is a no-op for `Allow`.

## Search Drivers

```php
ProductCatalog::search()          // configured default search driver
ProductCatalog::search('database')
ProductCatalog::search('scout')
ProductCatalog::extendSearch($name, $resolver)
```

`ScoutSearchDriver` requirements:

```php
// config/product-catalog.php
'model' => \App\Models\Product::class,  // top-level — read by search drivers, API controller, and all subsystems
'search' => [
    'driver' => env('PRODUCT_CATALOG_SEARCH_DRIVER', 'database'),
],
```

- `model` (top-level) must point to your application Product model
- that model must extend the package base Product model
- that model must use both `Laravel\Scout\Searchable` and `Aliziodev\ProductCatalog\Concerns\Searchable`, resolving the collision with
  `Searchable::toSearchableArray insteadof ScoutSearchable;` and `Searchable::makeAllSearchableUsing insteadof ScoutSearchable;` (a plain `use` of both is a fatal error)
- the default status filter uses `published()`, so scheduled products are hidden at query time even when they are in the index

`ProductSearchBuilder` helpers:

```php
ProductSearchBuilder::query($term)
ProductSearchBuilder::fromRequest($request)
    ->withStatus('draft')
    ->sortBy('price')
    ->sortAscending()
    ->paginate(24);
```

---

## Enums

### ProductType
```php
ProductType::Simple    // single SKU product
ProductType::Variable  // product with multiple variants
```

### ProductStatus
```php
ProductStatus::Draft       // not visible
ProductStatus::Published   // public; isLive() = true, isPublic() = true
ProductStatus::Private     // live but not listed; isLive() = true, isPublic() = false
ProductStatus::Archived    // not visible

// Helpers on the enum
$status->isPublic()  // true only for Published
$status->isLive()    // true for Published OR Private
$status->label()     // 'Draft' | 'Published' | 'Private' | 'Archived'
```

### InventoryPolicy
```php
InventoryPolicy::Track   // check actual stock
InventoryPolicy::Allow   // always in stock (digital, pre-order)
InventoryPolicy::Deny    // always out of stock; reserve() throws
```

### MovementType
```php
MovementType::Restock    // quantity increased (purchase, return)
MovementType::Deduction  // quantity decreased (sale, damage, commit)
MovementType::Adjustment // quantity delta corrected manually
MovementType::Set        // quantity set to absolute value
MovementType::Reserve    // reserved_quantity increased; quantity unchanged
MovementType::Release    // reserved_quantity decreased; quantity unchanged
```

### InventoryReason
```php
use Aliziodev\ProductCatalog\Enums\InventoryReason;

// Restock
InventoryReason::PURCHASE         // 'purchase'
InventoryReason::RETURN_ITEM      // 'return'
// Deduction
InventoryReason::SALE             // 'sale'
InventoryReason::DAMAGE           // 'damage'
InventoryReason::EXPIRY           // 'expiry'
// Adjustment / Set
InventoryReason::CORRECTION       // 'correction'
InventoryReason::STOCKTAKE        // 'stocktake'
// Reserve
InventoryReason::ORDER_PLACED     // 'order_placed'
InventoryReason::CART_HOLD        // 'cart_hold'
// Release
InventoryReason::ORDER_CANCELLED  // 'order_cancelled'
InventoryReason::CART_RELEASED    // 'cart_released'
InventoryReason::TIMEOUT          // 'timeout'
// Commit
InventoryReason::ORDER_FULFILLED  // 'order_fulfilled'
```

`$reason` is a free string, stored on the movement record as given. The config key
`inventory.movement_reasons` mirrors these constants as a reference list only — the package never
reads it and does not validate reasons against it. For app-specific reasons, define your own constants.

---

## Events

| Event | Key payload | Fired by |
|-------|-------------|----------|
| `ProductPublished` | `$event->product` | `$product->publish()` |
| `ProductArchived` | `$event->product` | `$product->archive()` |
| `InventoryAdjusted` | `variant`, `previousQuantity`, `newQuantity`, `reason`, `movement`; helper: `delta()` | `adjust()`, `set()`, `commit()` |
| `InventoryReserved` | `variant`, `type` (MovementType), `quantity`, `reservedBefore`, `reservedAfter`, `reason`, `movement`; helpers: `isReserve()`, `isRelease()` | `reserve()`, `release()` |
| `InventoryLowStock` | `variant`, `availableQuantity`, `threshold`, `movement` | When available crosses `low_stock_threshold` (from above) |
| `InventoryOutOfStock` | `variant`, `movement` | When available drops to 0 (takes precedence over LowStock) |

Namespace: `Aliziodev\ProductCatalog\Events\`

All six implement `Illuminate\Contracts\Events\ShouldDispatchAfterCommit`: listeners run after the outermost DB transaction commits, never if it rolls back, and immediately when no transaction is open. `Event::fake()` still records them immediately.

---

## Exceptions

```php
use Aliziodev\ProductCatalog\Exceptions\InventoryException;
use Aliziodev\ProductCatalog\Exceptions\ProductCatalogException;

InventoryException::insufficientStock($requested, $available);       // reserve(), adjust() dipping into reserved stock, or commit() when quantity < qty
InventoryException::insufficientReservation($requested, $reserved); // commit() when reserved < qty
InventoryException::purchaseNotAllowed($variantId);                  // reserve() on a Deny variant
InventoryException::invalidQuantity($quantity);                      // reserve(), release(), commit() with a quantity below 1
InventoryException::negativeQuantityNotAllowed();                    // set() with a negative quantity

// Duplicate manual slug — thrown automatically by HasSlug when a manually-set slug is already taken
ProductCatalogException::duplicateSlug($slug, $modelBasename);

// Other factory methods
ProductCatalogException::driverNotFound($driver);
ProductCatalogException::invalidProductType($type);
ProductCatalogException::cannotPublish($reason);
```

> **Note on slug uniqueness:** Auto-generated slugs (no `slug` field set on `create`) are always
> unique via a random `route_key` suffix and never throw. Only manually-set slugs are validated
> pre-insert/pre-update. Catch `ProductCatalogException` to surface a friendly error in your UI.

---

## HTTP Resources

| Resource | Namespace |
|----------|-----------|
| `ProductResource` | `Aliziodev\ProductCatalog\Http\Resources\ProductResource` |
| `ProductVariantResource` | `Aliziodev\ProductCatalog\Http\Resources\ProductVariantResource` |
| `BrandResource` | `Aliziodev\ProductCatalog\Http\Resources\BrandResource` |
| `CategoryResource` | `Aliziodev\ProductCatalog\Http\Resources\CategoryResource` |
| `TagResource` | `Aliziodev\ProductCatalog\Http\Resources\TagResource` |

`ProductResource` and `ProductVariantResource` include `meta` only when `product-catalog.routes.expose_meta` (`PRODUCT_CATALOG_ROUTES_EXPOSE_META`) is `true`; by default the key is absent.

---

## Built-in Routes (when enabled)

```
GET /catalog/products           → ProductController@index
GET /catalog/products/{slug}    → ProductController@show
GET /catalog/categories         → CategoryController@index   (root categories + children)
GET /catalog/brands             → BrandController@index      (all brands, by name)
```

Prefix from `routes.prefix` (default `catalog`), middleware from `routes.middleware`. Both product
endpoints list live products only (`published()`); `show` includes active variants only.

The routes are **unnamed** — there is no `catalog.products.index`. Use
`url(config('product-catalog.routes.prefix').'/products')`, or set `enabled = false` and register
your own named routes.

---

## Artisan Commands

```bash
php artisan catalog:install            # publish config + migrations (not interactive)
php artisan catalog:install --migrate  # same, then run migrate
php artisan catalog:seed-demo          # seed demo data (dev only)
```

---

## Database Tables (default prefix: catalog_)

| Table | Description |
|-------|-------------|
| `catalog_products` | Product header |
| `catalog_product_variants` | Variants (the sellable unit) |
| `catalog_product_options` | Option axes (Color, Size) |
| `catalog_product_option_values` | Option values (Red, XL) |
| `catalog_variant_option_values` | Pivot: variant ↔ option values |
| `catalog_inventory_items` | Stock per variant |
| `catalog_inventory_movements` | Audit trail of stock changes |
| `catalog_brands` | Brands |
| `catalog_categories` | Categories (with parent_id) |
| `catalog_tags` | Tags |
| `catalog_product_categories` | Pivot: product ↔ extra categories |
| `catalog_product_tags` | Pivot: product ↔ tags |

### Indexes

| Table | Column(s) | Index name | Migration |
|-------|-----------|------------|-----------|
| `catalog_products` | `name` | `idx_catalog_products_name` | `000004_create_catalog_products_table` |
| `catalog_inventory_items` | `policy` | `idx_catalog_inventory_items_policy` | `000011_create_catalog_inventory_items_table` |

> Installs created before v1.5.0 get both indexes from `2026_10_30_000001_sync_catalog_schema_for_upgraded_installs`.
> The `name` index speeds up `ORDER BY name` sorting and prefix LIKE searches.
> The `policy` index speeds up `inStock()` and `lowStock()` scopes which filter by policy value. 
