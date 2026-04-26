<p align="center"><img src="https://raw.githubusercontent.com/aliziodev/laravel-product-catalog/refs/heads/main/docs/art.png" alt="Laravel Product Catalog"></p>

<p align="center">
  <a href="https://codecov.io/gh/aliziodev/laravel-product-catalog"><img src="https://codecov.io/gh/aliziodev/laravel-product-catalog/graph/badge.svg?token=RCJT9CCXA8" alt="codecov"></a>
  <a href="https://github.com/aliziodev/laravel-product-catalog/actions"><img src="https://github.com/aliziodev/laravel-product-catalog/workflows/Tests/badge.svg" alt="Tests"></a>
  <a href="https://packagist.org/packages/aliziodev/laravel-product-catalog"><img src="https://img.shields.io/packagist/v/aliziodev/laravel-product-catalog.svg" alt="Latest Version on Packagist"></a>
</br>
  <a href="https://packagist.org/packages/aliziodev/laravel-product-catalog"><img src="https://img.shields.io/packagist/dt/aliziodev/laravel-product-catalog.svg" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/aliziodev/laravel-product-catalog"><img src="https://img.shields.io/packagist/php-v/aliziodev/laravel-product-catalog.svg" alt="PHP Version"></a>
  <a href="https://laravel.com/"><img src="https://img.shields.io/badge/Laravel-12.0%2B-orange.svg" alt="Laravel Version"></a>
  <a href="https://deepwiki.com/aliziodev/laravel-product-catalog"><img src="https://deepwiki.com/badge.svg" alt="Ask DeepWiki"></a>
</p>

A professional, variant-centric product catalog package for Laravel 12+. Designed to be a stable foundation for any application that needs structured product data — from a simple internal catalog to a full ecommerce storefront — without locking you into a specific architecture.

---

## Table of Contents

- [Suitable For](#suitable-for)
- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Basic Usage](#basic-usage)
  - [Products](#products)
  - [Variants & Options](#variants--options)
  - [Inventory](#inventory)
  - [Taxonomy](#taxonomy)
  - [Querying](#querying)
- [Slug Routing](#slug-routing)
- [API Resources](#api-resources)
- [Events](#events)
- [Inventory Policies](#inventory-policies)
- [Spatie Media Library Integration](#spatie-media-library-integration)
- [Custom Inventory Driver](#custom-inventory-driver)
- [Use-Case Docs](#use-case-docs)

---

## Suitable For

| Use Case | Description |
|---|---|
| **Product Catalog** | Display products with filtering, search, and SEO-friendly slug routing |
| **Online Store** | Storefront with prices, discount badges, per-variant stock, and cart-ready data |
| **Simple Ecommerce** | Order integration with reserve/release stock and an audit trail of stock movements |
| **Internal Catalog** | Internal product database with product codes, cost prices, and custom metadata |
| **Digital & Physical** | Mixed catalog — physical variants (tracked stock) and digital (unlimited) in one product |
| **Custom Inventory** | Stock already managed externally (ERP, WMS, your own table) — connect it via a single interface |

---

## Features

**Catalog**
- Products with lifecycle status: `draft` → `published` → `archived`
- Product code (`code`) as the parent SKU; per-variant SKUs for child variants
- Permanent slug routing — URLs stay valid even when the product name changes
- `ProductSearchBuilder` — fluent catalog-aware search with filters for category, brand, tags, price range, and stock status
- `fromRequest()` — map HTTP query-string params to the builder in one line
- Text search via LIKE (zero config) or MySQL FULLTEXT (opt-in via `search.fulltext = true`)
- Scout integration — plug in Algolia, Meilisearch, or Typesense via `ScoutSearchDriver`
- Taxonomy: Brand, Category (parent–child hierarchy), Tag — all with soft delete

**Variants & Options**
- `ProductVariant` as the primary sellable unit, not Product
- String-based options (Color, Size, etc.) with no separate master table required
- Auto-generated label from combined option values: `"Red / XL"`
- Auto-generate SKU from product code + option values
- Sale price, compare price (discount), and cost price per variant
- Physical dimensions (weight, length, width, height) for shipping calculation
- `meta` JSON for custom attributes without additional migrations

**Inventory**
- Three policies per variant: `track` (deduct stock), `allow` (always available), `deny` (unavailable)
- Soft-reserve (`reserved_quantity`) to hold stock while awaiting payment
- Full reservation lifecycle via the driver: `reserve()` → `release()` / `commit()`
- Low-stock threshold and alerts
- Append-only movement history (audit trail for every stock change, including reservations)
- `MovementType` enum: `Restock`, `Deduction`, `Adjustment`, `Set`, `Reserve`, `Release`
- `InventoryReason` constants for consistent audit trail reason strings
- Driver pattern — swap the stock system without changing application code
- Built-in: `database` (stock in DB) and `null` (always in stock, for digital products)

**Extensibility**
- Custom inventory driver — integrate your own stock system via a single interface
- No forced image schema — integrate spatie/laravel-medialibrary or any media solution you prefer
- Events: `ProductPublished`, `ProductArchived`, `InventoryAdjusted`
- Configurable table prefix — safe to install alongside any existing schema

---

## Why This Package

Most ecommerce packages bundle payment, cart, and order management alongside the catalog. This package does one thing well: **product catalog with variant-centric inventory**. You own the order flow.

- Product is a presentation entity. `ProductVariant` is the sellable unit.
- Inventory is pluggable — connect your own stock system via a single interface without touching your existing code.
- No forced image schema — integrate [spatie/laravel-medialibrary](https://spatie.be/docs/laravel-medialibrary) or your own solution.
- Configurable table prefix — safe to install alongside any existing schema.
- Slug routing that survives product renames (Shopee-style permanent route key).

---

## Requirements

- PHP **^8.3**
- Laravel **^12.0 | ^13.0**

---

## Quick Start

```bash
composer require aliziodev/laravel-product-catalog
```

```bash
php artisan catalog:install
```

```php
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Aliziodev\ProductCatalog\Enums\ProductType;
use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Aliziodev\ProductCatalog\Facades\ProductCatalog;

// 1. Create product
$product = Product::create(['name' => 'T-Shirt', 'code' => 'TS-001', 'type' => ProductType::Simple]);

// 2. Create variant
$variant = $product->variants()->create(['sku' => 'TS-001-WHT', 'price' => 150000, 'is_default' => true]);

// 3. Set stock
$variant->inventoryItem()->create(['quantity' => 100, 'policy' => InventoryPolicy::Track]);

// 4. Publish
$product->publish();

// 5. Query
Product::published()->inStock()->with('variants')->get();
```

## Installation

```bash
composer require aliziodev/laravel-product-catalog
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag=product-catalog-migrations
php artisan migrate
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=product-catalog-config
```

Or run the interactive installer:

```bash
php artisan catalog:install
```

---

## Configuration

```php
// config/product-catalog.php
return [

    // The Eloquent model used throughout the package (search drivers, API controller).
    // Override when extending the base Product model in your application.
    // Your model must extend Aliziodev\ProductCatalog\Models\Product.
    'model' => \Aliziodev\ProductCatalog\Models\Product::class,

    // Prefix for all package tables. Change BEFORE running migrations.
    'table_prefix' => env('PRODUCT_CATALOG_TABLE_PREFIX', 'catalog_'),

    'inventory' => [
        // Built-in: 'database' (tracks stock in DB), 'null' (always in stock).
        // Register custom drivers via ProductCatalog::extend().
        'driver' => env('PRODUCT_CATALOG_INVENTORY_DRIVER', 'database'),
    ],

    'slug' => [
        // Regenerate the slug prefix when the product name changes.
        'auto_generate'    => true,
        'separator'        => '-',
        // Length of the permanent random suffix (4–32). Recommended: 8.
        'route_key_length' => (int) env('PRODUCT_CATALOG_ROUTE_KEY_LENGTH', 8),
    ],

    'search' => [
        // Built-in: 'database' (default) or 'scout'.
        'driver' => env('PRODUCT_CATALOG_SEARCH_DRIVER', 'database'),
    ],

    'routes' => [
        // Set true to register the built-in read-only catalog API routes.
        'enabled'    => env('PRODUCT_CATALOG_ROUTES_ENABLED', false),
        'prefix'     => env('PRODUCT_CATALOG_ROUTES_PREFIX', 'catalog'),
        'middleware' => ['api'],
    ],
];
```

---

## Basic Usage

### Products

```php
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Enums\ProductType;

// Simple product (single SKU)
$product = Product::create([
    'name'              => 'Wireless Mouse',
    'code'              => 'WM-001',        // optional parent SKU / product code
    'type'              => ProductType::Simple,
    'short_description' => 'Ergonomic wireless mouse, 2.4 GHz.',
    'meta_title'        => 'Wireless Mouse — Best Price',
    'meta'              => ['warranty' => '1 year'],
]);

// Lifecycle
$product->publish();    // draft → published, fires ProductPublished event
$product->unpublish();  // published → draft
$product->archive();    // → archived, fires ProductArchived event

// State checks
$product->isPublished();
$product->isDraft();
$product->isArchived();
$product->isSimple();
$product->isVariable();
```

### Variants & Options

```php
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Aliziodev\ProductCatalog\Enums\ProductType;

// Variable product
$product = Product::create([
    'name' => 'Running Shoes',
    'code' => 'RS-AIR',
    'type' => ProductType::Variable,
]);

// Define options
$colorOption = $product->options()->create(['name' => 'Color', 'position' => 1]);
$red  = $colorOption->values()->create(['value' => 'Red',  'position' => 1]);
$blue = $colorOption->values()->create(['value' => 'Blue', 'position' => 2]);

$sizeOption = $product->options()->create(['name' => 'Size', 'position' => 2]);
$size42 = $sizeOption->values()->create(['value' => '42', 'position' => 1]);
$size43 = $sizeOption->values()->create(['value' => '43', 'position' => 2]);

// Create variant
$variant = ProductVariant::create([
    'product_id'    => $product->id,
    'sku'           => 'RS-AIR-RED-42',
    'price'         => 850000,
    'compare_price' => 1000000,    // original price (for sale badge)
    'cost_price'    => 500000,     // internal cost
    'weight'        => 0.350,
    'length'        => 30,
    'width'         => 15,
    'height'        => 12,
    'is_default'    => true,
    'is_active'     => true,
    'meta'          => ['barcode' => '8991234567890'],
]);

// Attach option values to variant
$variant->optionValues()->sync([$red->id, $size42->id]);

// Auto-generate SKU from product code + option values
$variant->load('optionValues');
$suggested = $product->buildVariantSku($variant); // "RS-AIR-RED-42"

// Human-readable label
$variant->displayName();        // "Red / 42"

// Pricing helpers
$variant->isOnSale();           // true — compare_price > price
$variant->discountPercentage(); // 15 (int)
```

### Inventory

```php
use Aliziodev\ProductCatalog\Facades\ProductCatalog;
use Aliziodev\ProductCatalog\Enums\InventoryReason;

$inventory = ProductCatalog::inventory(); // resolves configured driver

// Set absolute quantity
$inventory->set($variant, 50);

// Adjust (positive = restock, negative = deduct)
$inventory->adjust($variant, -5, InventoryReason::SALE, $order); // $order is optional reference model

// Query
$inventory->getQuantity($variant);        // available quantity (total − reserved)
$inventory->isInStock($variant);          // true
$inventory->canFulfill($variant, 10);     // true

// Built-in drivers:
// 'database' (default) — tracks stock in catalog_inventory_items
// 'null'               — always in stock, no DB writes (digital/unlimited goods)
// To use null driver: PRODUCT_CATALOG_INVENTORY_DRIVER=null in .env
// For per-variant unlimited stock use InventoryPolicy::Allow instead (more granular)

// Direct model helpers (InventoryItem)
$item = $variant->inventoryItem;
$item->availableQuantity();  // quantity - reserved_quantity
$item->reserve(3);           // increment reserved_quantity (no audit trail)
$item->release(3);           // decrement reserved_quantity (no audit trail)
$item->isLowStock();         // true if availableQuantity <= low_stock_threshold
```

### Reservation Lifecycle

Use the inventory driver for reserve/release/commit — these methods record movements and fire events.

```php
use Aliziodev\ProductCatalog\Facades\ProductCatalog;
use Aliziodev\ProductCatalog\Enums\InventoryReason;

$inventory = ProductCatalog::inventory();

// 1. Customer places order — hold stock
$inventory->reserve($variant, 3, InventoryReason::ORDER_PLACED, $order);
// reserved_quantity: +3, total quantity: unchanged, available: −3

// 2a. Order cancelled — release the hold
$inventory->release($variant, 3, InventoryReason::ORDER_CANCELLED, $order);
// reserved_quantity: −3, total quantity: unchanged, available: +3

// 2b. Order fulfilled — convert reservation to permanent deduction
$inventory->commit($variant, 3, InventoryReason::ORDER_FULFILLED, $order);
// reserved_quantity: −3, total quantity: −3, available: unchanged

// reserve() throws InventoryException when available stock < requested
// commit() throws InventoryException when reserved_quantity < requested
```

### InventoryReason

Use `InventoryReason` constants to keep reason strings consistent across your application.

```php
use Aliziodev\ProductCatalog\Enums\InventoryReason;

// Restock
InventoryReason::PURCHASE        // 'purchase'
InventoryReason::RETURN_ITEM     // 'return'

// Deduction
InventoryReason::SALE            // 'sale'
InventoryReason::DAMAGE          // 'damage'
InventoryReason::EXPIRY          // 'expiry'

// Adjustment / Set
InventoryReason::CORRECTION      // 'correction'
InventoryReason::STOCKTAKE       // 'stocktake'

// Reserve
InventoryReason::ORDER_PLACED    // 'order_placed'
InventoryReason::CART_HOLD       // 'cart_hold'

// Release
InventoryReason::ORDER_CANCELLED // 'order_cancelled'
InventoryReason::CART_RELEASED   // 'cart_released'
InventoryReason::TIMEOUT         // 'timeout'

// Commit
InventoryReason::ORDER_FULFILLED // 'order_fulfilled'
```

Add your own reason strings to `config/product-catalog.php`:

```php
'inventory' => [
    'movement_reasons' => [
        // built-in reasons ...
        'promotion',     // custom reason for your app
        'gift',
    ],
],
```

### Taxonomy

```php
use Aliziodev\ProductCatalog\Models\Brand;
use Aliziodev\ProductCatalog\Models\Category;
use Aliziodev\ProductCatalog\Models\Tag;

// Brand
$brand = Brand::create(['name' => 'Nike', 'slug' => 'nike']);
$product->update(['brand_id' => $brand->id]);

// Category (supports parent–child nesting)
$apparel  = Category::create(['name' => 'Apparel',  'slug' => 'apparel']);
$shoes    = Category::create(['name' => 'Shoes',    'slug' => 'shoes', 'parent_id' => $apparel->id]);

$product->update(['primary_category_id' => $shoes->id]);

// Assign multiple categories
$product->categories()->sync([$apparel->id, $shoes->id]);

// Tags
$tag = Tag::create(['name' => 'new-arrival', 'slug' => 'new-arrival']);
$product->tags()->attach($tag);
```

### Querying

```php
// Status scopes
Product::published()->get();
Product::draft()->get();

// Price range (active variants only)
$product->minPrice();      // float|null
$product->maxPrice();      // float|null
$product->priceRange();    // ['min' => 850000.0, 'max' => 1200000.0] | null

// Stock scope — products with at least one purchasable active variant
// NOTE: variants without an inventoryItem record are excluded from this scope.
// Always create an inventoryItem when creating a variant, even for Allow policy.
Product::inStock()->get();

// Search across name, code, short_description, and variant SKUs
Product::search('RS-AIR')->get();

// Filter
Product::forBrand($brand)->published()->get();
Product::withTag($tag)->inStock()->get();

// Low stock alert
use Aliziodev\ProductCatalog\Models\InventoryItem;

InventoryItem::lowStock()->with('variant.product')->get();
```

---

## Search

```php
use Aliziodev\ProductCatalog\Search\ProductSearchBuilder;

// Fluent API
ProductSearchBuilder::query('kemeja')
    ->inCategory('t-shirts')           // slug or ID
    ->withTags(['sale', 'new-arrival']) // AND logic, slug or ID
    ->forBrand('stylehouse')            // slug or ID
    ->priceBetween(50_000, 500_000)
    ->onlyInStock()
    ->sortBy('price')->sortAscending()
    ->paginate(24);

// Build from HTTP request — maps q, category, brand, tag/tags[],
// min_price, max_price, in_stock, type, sort_by, sort_direction
ProductSearchBuilder::fromRequest($request)->paginate(24);

// Control which relations are eager-loaded
ProductSearchBuilder::query('laptop')
    ->withRelations(['brand', 'primaryCategory', 'tags', 'defaultVariant'])
    ->paginate(20);
```

The default driver is `database` (Eloquent LIKE, no extra dependencies). Switch to `scout` driver for Meilisearch, Algolia, or Typesense — see [docs/scout-integration.md](docs/scout-integration.md).

```env
# .env
PRODUCT_CATALOG_SEARCH_DRIVER=database  # or: scout

# Optional — MySQL/MariaDB only, requires FULLTEXT index
PRODUCT_CATALOG_SEARCH_FULLTEXT=false
```

```php
// config/product-catalog.php
'model' => \App\Models\Product::class,  // top-level — used by all subsystems
'search' => [
    'driver' => env('PRODUCT_CATALOG_SEARCH_DRIVER', 'database'),
],
```

When using `scout`, set the top-level `model` key to your application Product model
that extends the package base model and uses both `Laravel\Scout\Searchable` and
`Aliziodev\ProductCatalog\Concerns\Searchable`. This same key is read by the database
search driver and the API controller — configure your extended model once, everywhere.

```php
// Custom search driver
use Aliziodev\ProductCatalog\Facades\ProductCatalog;

ProductCatalog::extendSearch('typesense', function ($app) {
    return new \App\Search\TypesenseSearchDriver;
});
```

```env
PRODUCT_CATALOG_SEARCH_DRIVER=typesense
```

---

## Slug Routing

Slugs use a permanent random `route_key` suffix (Shopee-style). Renaming a product regenerates the slug prefix but keeps the same route key, so old URLs still resolve.

```
/catalog/wireless-mouse-a1b2c3d4   ← original slug
/catalog/ergonomic-mouse-a1b2c3d4  ← after rename — same route_key suffix
```

```php
// Find by slug (both old and new slugs resolve)
$product = Product::findBySlug('ergonomic-mouse-a1b2c3d4');
$product = Product::findBySlugOrFail('ergonomic-mouse-a1b2c3d4');

// Scope variant
Product::published()->bySlug($slug)->firstOrFail();
```

Enable the built-in read-only API routes:

```php
// config/product-catalog.php
'routes' => [
    'enabled' => true,
    'prefix'  => 'catalog',
],
```

```
GET /catalog/products
GET /catalog/products/{slug}
```

---

## API Resources

```php
use Aliziodev\ProductCatalog\Http\Resources\ProductResource;
use Aliziodev\ProductCatalog\Http\Resources\ProductVariantResource;

$product = Product::with(['brand', 'primaryCategory', 'tags', 'variants'])->findOrFail($id);

return ProductResource::make($product);
```

Response shape:

```json
{
  "id": 1,
  "name": "Running Shoes",
  "code": "RS-AIR",
  "slug": "running-shoes-a1b2c3d4",
  "type": "variable",
  "status": "published",
  "featured_image_path": null,
  "brand": { "id": 1, "name": "Nike" },
  "variants": [
    {
      "id": 1,
      "sku": "RS-AIR-RED-42",
      "price": 850000,
      "compare_price": 1000000,
      "is_on_sale": true,
      "discount_percentage": 15,
      "weight": 0.35,
      "length": 30,
      "width": 15,
      "height": 12,
      "meta": { "barcode": "8991234567890" }
    }
  ]
}
```

---

## Events

| Event | Fired when |
|---|---|
| `ProductPublished` | `$product->publish()` |
| `ProductArchived` | `$product->archive()` |
| `InventoryAdjusted` | `adjust()`, `set()`, or `commit()` changes total quantity |
| `InventoryReserved` | `reserve()` or `release()` changes `reserved_quantity` |

```php
use Aliziodev\ProductCatalog\Events\ProductPublished;
use Aliziodev\ProductCatalog\Events\InventoryReserved;

class SendNewProductNotification
{
    public function handle(ProductPublished $event): void
    {
        // $event->product
    }
}

class HandleStockReservation
{
    public function handle(InventoryReserved $event): void
    {
        // $event->variant
        // $event->type          — MovementType::Reserve or MovementType::Release
        // $event->quantity      — positive for reserve, negative for release
        // $event->reservedBefore
        // $event->reservedAfter
        // $event->reason
        // $event->movement      — the InventoryMovement record
        // $event->isReserve()   — true when type is Reserve
        // $event->isRelease()   — true when type is Release
    }
}
```

---

## Inventory Policies

Set per `InventoryItem` via `policy` column:

| Policy | Behaviour |
|---|---|
| `track` | Checks actual quantity; denies when `quantity <= reserved_quantity` |
| `allow` | Always in stock — overselling permitted (digital goods, pre-order) |
| `deny` | Always out of stock — variant is unavailable |

```php
use Aliziodev\ProductCatalog\Enums\InventoryPolicy;

$variant->inventoryItem()->create([
    'quantity'           => 0,
    'policy'             => InventoryPolicy::Allow,  // never runs out
    'low_stock_threshold' => null,
]);
```

---

## Spatie Media Library Integration

This package intentionally excludes a built-in image gallery to stay compatible with any media solution your application already uses.

Install spatie/laravel-medialibrary:

```bash
composer require spatie/laravel-medialibrary
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```

Extend the `Product` model in your application:

```php
<?php

namespace App\Models;

use Aliziodev\ProductCatalog\Models\Product as BaseProduct;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends BaseProduct implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('featured')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('gallery');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(400)
            ->sharpen(5);

        $this->addMediaConversion('webp')
            ->format('webp')
            ->quality(80);
    }
}
```

> **Important:** Laravel's service container `bind()` does not affect Eloquent relationships.
> The package's `$variant->product()` is hardcoded to `BaseProduct::class` and will still
> return the base model. Choose one of the two approaches below.

**Approach A — Simple (recommended for most projects)**

Use `App\Models\Product` in all your app code. When you get a product through a variant relationship and need media, re-query with your model:

```php
// In your controllers / services — always use your extended model
use App\Models\Product;

$product = Product::with('variants')->findOrFail($id);
$product->getFirstMediaUrl('featured', 'thumb'); // ✓ works

// When coming from a variant relationship, re-query:
$product = App\Models\Product::find($variant->product_id);
$product->getFirstMediaUrl('featured', 'thumb'); // ✓ works
```

**Approach B — Override the relationship (complete solution)**

Also extend `ProductVariant` to return your `Product`:

```php
// app/Models/ProductVariant.php
namespace App\Models;

use Aliziodev\ProductCatalog\Models\ProductVariant as BaseVariant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends BaseVariant
{
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class); // App\Models\Product
    }
}
```

Then use `App\Models\ProductVariant` everywhere in your app code.

Upload and retrieve:

```php
// Upload featured image
$product->addMediaFromRequest('image')->toMediaCollection('featured');

// Upload gallery
$product->addMediaFromRequest('gallery')->toMediaCollection('gallery');

// Get URLs
$product->getFirstMediaUrl('featured', 'thumb');
$product->getMedia('gallery')->map->getUrl('webp');
```

---

## Custom Inventory Driver

If your application already has its own stock system — your own `inventories` table, an ERP, or a third-party WMS — you don't have to use the package's `catalog_inventory_items` table. Implement `InventoryProviderInterface` and the package will talk to your system instead.

```php
<?php

namespace App\Inventory;

use Aliziodev\ProductCatalog\Contracts\InventoryProviderInterface;
use Aliziodev\ProductCatalog\Exceptions\InventoryException;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use App\Models\Inventory; // your own inventory model
use Illuminate\Database\Eloquent\Model;

class AppInventoryProvider implements InventoryProviderInterface
{
    public function getQuantity(ProductVariant $variant): int
    {
        return Inventory::where('sku', $variant->sku)->value('quantity') ?? 0;
    }

    public function isInStock(ProductVariant $variant): bool
    {
        return $this->getQuantity($variant) > 0;
    }

    public function canFulfill(ProductVariant $variant, int $quantity): bool
    {
        return $this->getQuantity($variant) >= $quantity;
    }

    public function adjust(
        ProductVariant $variant,
        int $delta,
        string $reason = '',
        ?Model $reference = null,
    ): void {
        $record = Inventory::where('sku', $variant->sku)->firstOrFail();
        $newQty = $record->quantity + $delta;

        if ($newQty < 0) {
            throw InventoryException::insufficientStock(abs($delta), $record->quantity);
        }

        $record->update(['quantity' => $newQty]);
    }

    public function set(
        ProductVariant $variant,
        int $quantity,
        string $reason = '',
        ?Model $reference = null,
    ): void {
        Inventory::updateOrCreate(
            ['sku'      => $variant->sku],
            ['quantity' => max(0, $quantity)]
        );
    }

    public function reserve(
        ProductVariant $variant,
        int $quantity,
        string $reason = '',
        ?Model $reference = null,
    ): void {
        // implement reservation against your external system
    }

    public function release(
        ProductVariant $variant,
        int $quantity,
        string $reason = '',
        ?Model $reference = null,
    ): void {
        // implement release against your external system
    }

    public function commit(
        ProductVariant $variant,
        int $quantity,
        string $reason = '',
        ?Model $reference = null,
    ): void {
        // implement commit (reservation → permanent deduction) against your external system
    }
}
```

Register the driver in a `ServiceProvider`:

```php
use Aliziodev\ProductCatalog\Facades\ProductCatalog;

public function boot(): void
{
    ProductCatalog::extend('app', function ($app) {
        return new \App\Inventory\AppInventoryProvider;
    });
}
```

Activate via `.env`:

```env
PRODUCT_CATALOG_INVENTORY_DRIVER=app
```

For more examples (ERP/WMS API, fallback strategy) see [docs/custom-inventory-provider.md](docs/custom-inventory-provider.md).

---

## Testing

When writing tests for code that uses this package, set up your test case with migrations and factories:

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

For tests that need the inventory facade, swap to the `null` driver so no DB records are required:

```php
// tests/Feature/CheckoutTest.php
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Aliziodev\ProductCatalog\Enums\InventoryPolicy;

it('can add item to cart', function () {
    $variant = ProductVariant::factory()->create(['price' => 150000]);

    // Use Allow policy — no inventory record needed
    $variant->inventoryItem()->create(['quantity' => 0, 'policy' => InventoryPolicy::Allow]);

    // ... your test assertions
});
```

To override the inventory driver in a specific test:

```php
config(['product-catalog.inventory.driver' => 'null']);
```

---

## Use-Case Docs

Detailed guides for specific scenarios:

| Guide | Description |
|---|---|
| [Product Catalog](docs/product-catalog.md) | Read-only catalog with filtering, search, and slug routing |
| [Online Store](docs/online-store.md) | Storefront with price display, stock badges, and cart-ready data |
| [Simple Ecommerce](docs/ecommerce-simple.md) | Minimal ecommerce setup with order integration |
| [Internal Catalog](docs/internal-catalog.md) | B2B / internal product database with cost price and meta fields |
| [Digital & Physical Products](docs/digital-physical-listing.md) | Mixed catalog with unlimited-stock and downloadable variants |
| [Custom Inventory Provider](docs/custom-inventory-provider.md) | Connect ERP, WMS, Redis, or any external stock source |
| [Scout Integration](docs/scout-integration.md) | Full-text search with Algolia, Meilisearch, or Typesense via Laravel Scout |
| [Configuration Reference](docs/configuration.md) | Deep dive into every config key with pitfalls and gotchas |

---

## License

MIT — see [LICENSE](LICENSE).
