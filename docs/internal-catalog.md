# Use Case: Internal Catalog

A B2B or internal product database — for procurement teams, warehouse staff, or sales reps — where cost price, product codes, and metadata are as important as the public-facing presentation.

---

## What You'll Build

- Internal product lookup by code and SKU
- Cost price tracking and margin calculation
- Custom metadata per product and variant (compliance codes, certifications, etc.)
- Low-stock monitoring and restocking workflow
- Category-based product organization for internal navigation

---

## 1. Product Structure for Internal Use

```php
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Aliziodev\ProductCatalog\Enums\ProductType;

$product = Product::create([
    'name'              => 'Laptop Stand Pro',
    'code'              => 'LSP-2024',         // internal product code / parent SKU
    'type'              => ProductType::Simple,
    'short_description' => 'Adjustable aluminum laptop stand.',
    'meta' => [
        'supplier'         => 'PT Maju Jaya',
        'supplier_code'    => 'MJ-LS-001',
        'hs_code'          => '8473.30',        // customs code for import
        'certifications'   => ['CE', 'RoHS'],
        'reorder_point'    => 20,
        'lead_time_days'   => 14,
    ],
]);

$variant = ProductVariant::create([
    'product_id' => $product->id,
    'sku'        => 'LSP-2024-SLV',   // silver variant
    'price'      => 450000,            // selling price
    'cost_price' => 280000,            // purchase / landed cost
    'weight'     => 0.850,
    'is_default' => true,
    'meta' => [
        'barcode'      => '8991234567890',
        'customs_code' => 'HS-8473',
    ],
]);
```

## 2. Cost Margin Calculation

`cost_price` is stored on the variant. Calculate margin in your application:

```php
function grossMargin(ProductVariant $variant): ?float
{
    $cost = (float) $variant->cost_price;
    $price = (float) $variant->price;

    if ($cost <= 0 || $price <= 0) {
        return null;
    }

    return round(($price - $cost) / $price * 100, 2); // percentage
}

$margin = grossMargin($variant); // e.g. 37.78%
```

## 3. Search by Code or SKU

```php
// Search across name, product code, description, and variant SKUs
$results = Product::search('LSP-2024')->with('variants')->get();

// Direct lookup by product code
$product = Product::where('code', 'LSP-2024')->firstOrFail();

// Direct lookup by SKU
$variant = ProductVariant::where('sku', 'LSP-2024-SLV')->with('product')->firstOrFail();
```

## 4. Internal Inventory Management

```php
use Aliziodev\ProductCatalog\Facades\ProductCatalog;

$inventory = ProductCatalog::inventory();

// Initial stock entry (goods received)
$inventory->set($variant, 100, 'goods_received');

// Restock from purchase order
$inventory->adjust($variant, 50, 'purchase_order', $purchaseOrder);

// Damage / write-off
$inventory->adjust($variant, -3, 'damaged');

// Physical count correction
$inventory->set($variant, 95, 'physical_count');
```

## 5. Low-Stock & Reorder Monitoring

```php
use Aliziodev\ProductCatalog\Models\InventoryItem;

// Set threshold when creating inventory item
$variant->inventoryItem()->create([
    'quantity'            => 100,
    'reserved_quantity'   => 0,
    'low_stock_threshold' => 20,    // alert when <= 20 available
    'policy'              => \Aliziodev\ProductCatalog\Enums\InventoryPolicy::Track,
]);

// Query all variants below threshold — run as a scheduled job
$alerts = InventoryItem::lowStock()
    ->with(['variant' => fn ($q) => $q->with('product')])
    ->get()
    ->map(function ($item) {
        return [
            'product_code'      => $item->variant->product->code,
            'sku'               => $item->variant->sku,
            'available'         => $item->availableQuantity(),
            'threshold'         => $item->low_stock_threshold,
            'reorder_point'     => $item->variant->product->meta['reorder_point'] ?? null,
            'lead_time_days'    => $item->variant->product->meta['lead_time_days'] ?? null,
        ];
    });
```

## 6. Category Organization

```php
use Aliziodev\ProductCatalog\Models\Category;

// Internal category hierarchy
$electronics = Category::create(['name' => 'Electronics',      'slug' => 'electronics']);
$peripherals  = Category::create(['name' => 'Peripherals',     'slug' => 'peripherals',     'parent_id' => $electronics->id]);
$accessories  = Category::create(['name' => 'Accessories',     'slug' => 'accessories',     'parent_id' => $peripherals->id]);

$product->update(['primary_category_id' => $accessories->id]);

// Browse all products in a category
Product::where('primary_category_id', $accessories->id)->get();
```

## 7. Meta for Custom Attributes

The `meta` JSON column on both `Product` and `ProductVariant` lets you store domain-specific data without app-level migrations:

```php
// Product-level meta
$product->update([
    'meta' => [
        'supplier'      => 'PT Maju Jaya',
        'reorder_point' => 20,
        'lead_time_days'=> 14,
        'certifications'=> ['CE', 'RoHS'],
    ],
]);

// Variant-level meta
$variant->update([
    'meta' => [
        'barcode'      => '8991234567890',
        'customs_code' => 'HS-8473',
        'shelf_location'=> 'A-12-3',
    ],
]);

// Read
$location = $variant->meta['shelf_location'] ?? 'unassigned';
```

---

## Relevant Model API

| Field / Method | Description |
|---|---|
| `Product::code` | Internal product code / parent SKU |
| `ProductVariant::cost_price` | Purchase / landed cost |
| `Product::search($term)` | Search by name, code, SKU |
| `$product->meta` | JSON — store any internal attributes |
| `$variant->meta` | JSON — barcode, shelf location, customs code |
| `InventoryItem::lowStock()` | Scope for reorder dashboard |
| `$item->low_stock_threshold` | Configurable per variant |
| `$item->availableQuantity()` | Net available after reservations |
