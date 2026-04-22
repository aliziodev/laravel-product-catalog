# Use Case: Digital & Physical Product Listing

A mixed catalog where some products are physical (tracked inventory, weight, dimensions) and others are digital (unlimited stock, instant delivery, no shipping). This package handles both with the `InventoryPolicy` enum per variant.

---

## What You'll Build

- Physical products with weight, dimensions, and tracked stock
- Digital products that never go out of stock
- Mixed product (e.g. a course with a physical workbook variant)
- Filtering by availability regardless of product type
- Download-link or delivery metadata stored in `meta`

---

## 1. Physical Product

```php
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Aliziodev\ProductCatalog\Enums\ProductType;

$book = Product::create([
    'name'  => 'Laravel Deep Dive (Print Edition)',
    'code'  => 'LDD-PRINT',
    'type'  => ProductType::Simple,
    'meta'  => ['format' => 'paperback', 'pages' => 320],
]);

$book->publish();

$physicalVariant = ProductVariant::create([
    'product_id' => $book->id,
    'sku'        => 'LDD-PRINT-001',
    'price'      => 250000,
    'weight'     => 0.450,    // kg
    'length'     => 23,       // cm
    'width'      => 15,
    'height'     => 2,
    'is_default' => true,
]);

// Create tracked inventory item
$physicalVariant->inventoryItem()->create([
    'quantity'            => 200,
    'reserved_quantity'   => 0,
    'low_stock_threshold' => 30,
    'policy'              => InventoryPolicy::Track,
]);
```

## 2. Digital Product — Unlimited Stock

```php
$ebook = Product::create([
    'name' => 'Laravel Deep Dive (Digital Edition)',
    'code' => 'LDD-EBOOK',
    'type' => ProductType::Simple,
    'meta' => ['format' => 'pdf+epub', 'file_size_mb' => 18],
]);

$ebook->publish();

$digitalVariant = ProductVariant::create([
    'product_id' => $ebook->id,
    'sku'        => 'LDD-EBOOK-001',
    'price'      => 99000,
    'is_default' => true,
    'meta'       => [
        'download_url' => null,   // populated after purchase by your app
        'license_type' => 'single-user',
    ],
]);

// Always in stock — no physical quantity tracking needed
$digitalVariant->inventoryItem()->create([
    'quantity'  => 0,           // irrelevant for Allow policy
    'policy'    => InventoryPolicy::Allow,
]);
```

## 3. Mixed Product — Physical + Digital Variants

```php
$course = Product::create([
    'name' => 'Laravel Mastery Bundle',
    'type' => ProductType::Variable,
]);

$formatOption = $course->options()->create(['name' => 'Format', 'position' => 1]);
$digital  = $formatOption->values()->create(['value' => 'Digital',          'position' => 1]);
$physical = $formatOption->values()->create(['value' => 'Print + Digital',  'position' => 2]);

// Digital variant
$dvVariant = ProductVariant::create([
    'product_id' => $course->id,
    'sku'        => 'LMB-DIG',
    'price'      => 149000,
]);
$dvVariant->optionValues()->attach($digital);
$dvVariant->inventoryItem()->create(['policy' => InventoryPolicy::Allow, 'quantity' => 0]);

// Physical bundle variant
$pbVariant = ProductVariant::create([
    'product_id' => $course->id,
    'sku'        => 'LMB-PRINT',
    'price'      => 349000,
    'weight'     => 0.450,
]);
$pbVariant->optionValues()->attach($physical);
$pbVariant->inventoryItem()->create([
    'policy'              => InventoryPolicy::Track,
    'quantity'            => 150,
    'low_stock_threshold' => 20,
]);

$course->publish();
```

## 4. Distinguishing Digital from Physical at Runtime

There is no `is_digital` flag — use `InventoryPolicy` and dimensions as the signal:

```php
function isDigitalVariant(ProductVariant $variant): bool
{
    $item = $variant->inventoryItem;

    // Digital = Allow policy + no weight/dimensions
    return $item?->policy === \Aliziodev\ProductCatalog\Enums\InventoryPolicy::Allow
        && $variant->weight === null;
}
```

Or store an explicit flag in `meta`:

```php
$variant->meta['is_digital'] = true;
```

## 5. Post-Purchase: Deliver Download Link

Your app handles delivery. Use `meta` to attach the download token after order is placed:

```php
// In your order fulfilled listener
$variant->update([
    'meta' => array_merge($variant->meta ?? [], [
        'download_token' => Str::uuid(),
        'download_expires_at' => now()->addDays(30)->toISOString(),
    ]),
]);
```

Or store it on your `order_items` table — do not store customer-specific tokens on the variant itself. The `meta` on the variant is catalog metadata (license type, file format), not per-customer data.

## 6. Shipping Calculation — Physical Variants

```php
$physicalItems = $order->items->filter(function ($item) {
    $variant = ProductVariant::find($item->variant_id);
    return $variant?->weight !== null;
});

$totalWeight = $physicalItems->sum(function ($item) {
    $variant = ProductVariant::find($item->variant_id);
    return (float) $variant->weight * $item->quantity;
});

// Pass $totalWeight to your shipping rate API
```

## 7. Filtering in Catalog

```php
// All in-stock products (works for both digital and physical)
Product::published()->inStock()->get();

// Only physical products (have at least one variant with weight set)
Product::published()
    ->whereHas('variants', fn ($q) => $q->whereNotNull('weight'))
    ->get();
```

---

## Relevant Model API

| Field / Enum | Description |
|---|---|
| `InventoryPolicy::Allow` | Unlimited stock — for digital goods |
| `InventoryPolicy::Track` | Quantity-tracked — for physical goods |
| `InventoryPolicy::Deny` | Variant unavailable |
| `$variant->weight` | Physical shipping weight (kg) |
| `$variant->length / width / height` | Physical dimensions (cm) |
| `$variant->meta` | Store `license_type`, `file_format`, `is_digital`, etc. |
| `$product->meta` | Store `format`, `pages`, `file_size_mb`, etc. |
| `Product::inStock()` | Works correctly across both policy types |
