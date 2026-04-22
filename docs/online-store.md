# Use Case: Online Store

A customer-facing storefront with price display, stock badges, variant selection, and data shaped for adding items to a cart. This package covers the catalog side; you own the cart and order logic.

---

## What You'll Build

- Product listing with sale badges and stock indicators
- Variant selector (Color × Size matrix)
- Price display with compare price and discount percentage
- Stock availability per variant
- Cart-ready payload from catalog data

---

## 1. Listing Page — Price & Stock

```php
use Aliziodev\ProductCatalog\Models\Product;

$products = Product::published()
    ->inStock()
    ->with([
        'brand',
        'variants' => fn ($q) => $q->active()->with('inventoryItem'),
    ])
    ->paginate(20);

foreach ($products as $product) {
    $range = $product->priceRange(); // ['min' => 150000.0, 'max' => 250000.0]

    // For simple products: single variant price
    $default = $product->variants->firstWhere('is_default', true);
    echo $default?->price;
    echo $default?->isOnSale();           // show "SALE" badge
    echo $default?->discountPercentage(); // "15% OFF"
}
```

## 2. Product Detail — Variant Selector

Load everything needed to build a variant picker on the frontend:

```php
$product = Product::published()
    ->with([
        'options.values',
        'variants' => fn ($q) => $q->active()->with(['optionValues', 'inventoryItem']),
    ])
    ->bySlug($slug)
    ->firstOrFail();
```

Shape the option matrix for the frontend:

```php
// Build option → value → variant map
$matrix = [];

foreach ($product->variants as $variant) {
    $key = $variant->optionValues->pluck('id')->sort()->join('-');
    $matrix[$key] = [
        'variant_id'          => $variant->id,
        'sku'                 => $variant->sku,
        'price'               => (float) $variant->price,
        'compare_price'       => $variant->compare_price ? (float) $variant->compare_price : null,
        'is_on_sale'          => $variant->isOnSale(),
        'discount_percentage' => $variant->discountPercentage(),
        'in_stock'            => $variant->inventoryItem?->isInStock() ?? false,
        'available_quantity'  => $variant->inventoryItem?->availableQuantity() ?? 0,
    ];
}
```

## 3. Stock Badge

```php
$item = $variant->inventoryItem;

match(true) {
    $item === null                       => 'Unknown',
    $item->policy->value === 'deny'      => 'Unavailable',
    $item->policy->value === 'allow'     => 'In Stock',
    $item->availableQuantity() === 0     => 'Out of Stock',
    $item->isLowStock()                  => "Only {$item->availableQuantity()} left",
    default                              => 'In Stock',
};
```

## 4. Cart-Ready Payload

When a customer selects a variant and clicks "Add to Cart", extract the data your cart system needs:

```php
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Aliziodev\ProductCatalog\Facades\ProductCatalog;

$variant = ProductVariant::with(['product', 'inventoryItem', 'optionValues'])->findOrFail($variantId);

$inventory = ProductCatalog::inventory();

if (! $inventory->canFulfill($variant, $qty)) {
    abort(422, 'Insufficient stock.');
}

// Data to pass to your cart
$cartItem = [
    'variant_id'    => $variant->id,
    'product_id'    => $variant->product_id,
    'name'          => $variant->product->name,
    'option_label'  => $variant->displayName(),   // "Red / 42"
    'sku'           => $variant->sku,
    'price'         => (float) $variant->price,
    'quantity'      => $qty,
    'weight'        => (float) $variant->weight,
    'meta'          => $variant->meta,
];
```

## 5. Reserve Stock for an Order

Once the order is confirmed, reserve the quantity so concurrent orders don't oversell:

```php
// After order is created
$variant->inventoryItem->reserve($qty);

// After order is cancelled or payment fails
$variant->inventoryItem->release($qty);

// After order ships (actual deduction via inventory provider)
ProductCatalog::inventory()->adjust($variant, -$qty, 'shipped', $order);
```

## 6. Low Stock Alert

```php
use Aliziodev\ProductCatalog\Models\InventoryItem;

// Schedule this as a daily job
$lowStock = InventoryItem::lowStock()
    ->with('variant.product')
    ->get();

foreach ($lowStock as $item) {
    $variant = $item->variant;
    $product = $variant->product;

    // Notify purchasing team
    // Notification::send(...);
}
```

---

## Relevant Model API

| Method | Description |
|---|---|
| `$variant->isOnSale()` | `compare_price > price` |
| `$variant->discountPercentage()` | Integer discount % |
| `$variant->displayName()` | "Red / 42" |
| `$item->availableQuantity()` | `quantity - reserved_quantity` |
| `$item->isLowStock()` | Below `low_stock_threshold` |
| `$item->reserve($qty)` | Increment reserved |
| `$item->release($qty)` | Decrement reserved |
| `$inventory->canFulfill($variant, $qty)` | Pre-purchase check |
| `$inventory->adjust($variant, $delta)` | Deduct or restock |
