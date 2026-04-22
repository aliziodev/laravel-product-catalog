# API Reference — laravel-product-catalog

Quick lookup for all scopes, methods, enums, events, exceptions, and DB tables.

---

## Product — Scopes

| Scope | Description |
|-------|-------------|
| `Product::published()` | Status = published |
| `Product::draft()` | Status = draft |
| `Product::archived()` | Status = archived |
| `Product::inStock()` | Has at least one purchasable active variant (requires inventoryItem row!) |
| `Product::search($term)` | Local Eloquent scope search: name, code, short_description, variant SKUs |
| `Product::forBrand($brand)` | Filter by brand model instance |
| `Product::withTag($tag)` | Filter by tag model instance |
| `Product::bySlug($slug)` | Filter by slug (matches on the permanent route_key suffix) |

## Product — Methods

| Method | Return | Description |
|--------|--------|-------------|
| `$product->publish()` | void | draft → published |
| `$product->unpublish()` | void | published → draft |
| `$product->archive()` | void | → archived |
| `$product->isPublished()` | bool | |
| `$product->isDraft()` | bool | |
| `$product->isArchived()` | bool | |
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

---

## ProductVariant — Methods

| Method | Return | Description |
|--------|--------|-------------|
| `$variant->displayName()` | string | "Red / 42" — combined option values label |
| `$variant->isOnSale()` | bool | compare_price > price |
| `$variant->discountPercentage()` | int | Discount percentage (integer) |

## ProductVariant — Relationships

```php
$variant->product()           // BelongsTo Product
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

$inv->getQuantity($variant)                           // int
$inv->isInStock($variant)                             // bool
$inv->canFulfill($variant, $qty)                      // bool
$inv->adjust($variant, $delta, $reason, $reference)   // void
$inv->set($variant, $qty, $reason, $reference)        // void

ProductCatalog::extend($name, $resolver)  // register a custom driver
```

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
- that model must use both `Laravel\Scout\Searchable` and `Aliziodev\ProductCatalog\Concerns\Searchable`

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
ProductStatus::Draft
ProductStatus::Published
ProductStatus::Archived
```

### InventoryPolicy
```php
InventoryPolicy::Track   // check actual stock
InventoryPolicy::Allow   // always in stock (digital, pre-order)
InventoryPolicy::Deny    // always out of stock
```

### MovementType
```php
MovementType::Adjustment
MovementType::Sale
MovementType::Return
MovementType::Restock
// (see enum for full list)
```

---

## Events

| Event | Payload | When |
|-------|---------|------|
| `ProductPublished` | `$event->product` | `$product->publish()` |
| `ProductArchived` | `$event->product` | `$product->archive()` |
| `InventoryAdjusted` | `$event->variant, $event->delta` | `DatabaseInventoryProvider::adjust()` |

Namespace: `Aliziodev\ProductCatalog\Events\`

---

## Exceptions

```php
use Aliziodev\ProductCatalog\Exceptions\InventoryException;
use Aliziodev\ProductCatalog\Exceptions\ProductCatalogException;

// Insufficient stock
InventoryException::insufficientStock($variant, $requestedQty);

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

---

## Built-in Routes (when enabled)

```
GET /catalog/products           → ProductController@index
GET /catalog/products/{slug}    → ProductController@show
```

Route names: `catalog.products.index`, `catalog.products.show`

---

## Artisan Commands

```bash
php artisan catalog:install    # interactive: publish config + migrate
php artisan catalog:seed-demo  # seed demo data (dev only)
```

---

## Database Tables (default prefix: catalog_)

| Table | Description |
|-------|-------------|
| `catalog_products` | Product header |
| `catalog_product_variants` | Variants (the sellable unit) |
| `catalog_product_options` | Option axes (Color, Size) |
| `catalog_product_option_values` | Option values (Red, XL) |
| `catalog_product_option_value_variant` | Pivot: variant ↔ option values |
| `catalog_inventory_items` | Stock per variant |
| `catalog_inventory_movements` | Audit trail of stock changes |
| `catalog_brands` | Brands |
| `catalog_categories` | Categories (with parent_id) |
| `catalog_tags` | Tags |
| `catalog_product_categories` | Pivot: 
