# Use Case Patterns

Read this file when the user needs a specific pattern based on their use case context.

---

## 1. Product Catalog (Read-Only Public)

For marketing sites, brand showrooms, or any application that displays products without a checkout flow.

```php
// CatalogController
public function index(Request $request): AnonymousResourceCollection
{
    $query = Product::published()
        ->with(['brand', 'primaryCategory', 'variants' => fn ($q) => $q->active()]);

    if ($request->filled('q'))         $query->search($request->string('q')->toString());
    if ($request->filled('brand'))     $query->forBrand(Brand::where('slug', $request->brand)->firstOrFail());
    if ($request->filled('category'))  $query->where('primary_category_id', Category::where('slug', $request->category)->value('id'));
    if ($request->filled('tag'))       $query->withTag(Tag::where('slug', $request->tag)->firstOrFail());
    if ($request->boolean('in_stock')) $query->inStock();

    return ProductResource::collection($query->latest()->paginate(24));
}

public function show(string $slug): ProductResource
{
    $product = Product::published()
        ->with(['brand', 'primaryCategory', 'tags', 'variants.optionValues', 'options.values'])
        ->bySlug($slug)
        ->firstOrFail();

    return ProductResource::make($product);
}
```

**Category tree:**
```php
$tree = Category::whereNull('parent_id')->with('children')->orderBy('position')->get();
```

**Sitemap:**
```php
Product::published()->select(['slug', 'updated_at'])->cursor();
```

---

## 2. Online Store (Storefront with Cart)

For storefronts with price display, sale badges, stock indicators, and cart-ready data.

### Listing with Sale Badge

```php
$products = Product::published()->inStock()
    ->with(['brand', 'variants' => fn ($q) => $q->active()->with('inventoryItem')])
    ->paginate(20);

foreach ($products as $product) {
    $default = $product->variants->firstWhere('is_default', true);
    $range   = $product->priceRange(); // ['min' => x, 'max' => y]

    echo $default?->price;
    echo $default?->isOnSale();           // "SALE" badge
    echo $default?->discountPercentage(); // "15% OFF"
}
```

### Variant Selector Matrix

```php
$product = Product::published()
    ->with(['options.values', 'variants' => fn ($q) => $q->active()->with(['optionValues', 'inventoryItem'])])
    ->bySlug($slug)->firstOrFail();

// Build matrix for frontend
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

### Stock Badge Logic

```php
$item = $variant->inventoryItem;

$badge = match(true) {
    $item === null                   => 'Unknown',
    $item->policy->value === 'deny'  => 'Unavailable',
    $item->policy->value === 'allow' => 'In Stock',
    $item->availableQuantity() === 0 => 'Out of Stock',
    $item->isLowStock()              => "Only {$item->availableQuantity()} left",
    default                          => 'In Stock',
};
```

### Cart-Ready Payload

```php
$variant = ProductVariant::with(['product', 'inventoryItem', 'optionValues'])->findOrFail($variantId);

if (! ProductCatalog::inventory()->canFulfill($variant, $qty)) {
    abort(422, 'Insufficient stock.');
}

$cartItem = [
    'variant_id'   => $variant->id,
    'product_id'   => $variant->product_id,
    'name'         => $variant->product->name,
    'option_label' => $variant->displayName(),  // "Red / 42"
    'sku'          => $variant->sku,
    'price'        => (float) $variant->price,
    'quantity'     => $qty,
    'weight'       => (float) $variant->weight,
    'meta'         => $variant->meta,
];
```

---

## 3. Simple Ecommerce (with Order Integration)

### 3-State Inventory Flow

```
Order CREATED  → $inventory->reserve($variant, $qty, InventoryReason::ORDER_PLACED, $order)
Payment OK     → $inventory->commit($variant, $qty, InventoryReason::ORDER_FULFILLED, $order)
Payment FAIL   → $inventory->release($variant, $qty, InventoryReason::ORDER_CANCELLED, $order)
Order SHIPPED  → (no further inventory change)
```

> All driver write methods run inside `DB::transaction + lockForUpdate` — no race condition.
> See `docs/ecommerce-simple.md` for the full end-to-end guide.

### CreateOrderAction

```php
use Aliziodev\ProductCatalog\Enums\InventoryReason;

class CreateOrderAction
{
    public function execute(array $items, int $userId): Order
    {
        return DB::transaction(function () use ($items, $userId) {
            $inventory = ProductCatalog::inventory();

            // 1. Validate all items before touching anything
            foreach ($items as $item) {
                $variant = ProductVariant::with('inventoryItem')->findOrFail($item['variant_id']);
                if (! $inventory->canFulfill($variant, $item['quantity'])) {
                    throw new \Exception("Insufficient stock: {$variant->sku}");
                }
            }

            // 2. Create order header
            $order = Order::create(['user_id' => $userId, 'status' => 'pending', 'total' => 0]);
            $total = 0;

            // 3. Create order items + soft-reserve via driver (records movement + fires event)
            foreach ($items as $item) {
                $variant = ProductVariant::with(['product', 'inventoryItem', 'optionValues'])->find($item['variant_id']);
                $order->items()->create([
                    'variant_id'   => $variant->id,
                    'sku'          => $variant->sku,
                    'product_name' => $variant->product->name,
                    'option_label' => $variant->displayName(),
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $variant->price, // snapshot at order time
                ]);
                $inventory->reserve($variant, $item['quantity'], InventoryReason::ORDER_PLACED, $order);
                $total += $variant->price * $item['quantity'];
            }

            $order->update(['total' => $total]);
            return $order;
        });
    }
}
```

### PaymentConfirmedAction

```php
class PaymentConfirmedAction
{
    public function execute(Order $order): void
    {
        if ($order->status !== 'pending') return;

        DB::transaction(function () use ($order) {
            $inventory = ProductCatalog::inventory();

            foreach ($order->items as $item) {
                $variant = ProductVariant::find($item->variant_id);
                // commit = decrement both quantity AND reserved_quantity atomically
                $inventory->commit($variant, $item->quantity, InventoryReason::ORDER_FULFILLED, $order);
            }

            $order->update(['status' => 'paid']);
        });
    }
}
```

### OrderCancelledAction

```php
class OrderCancelledAction
{
    public function execute(Order $order): void
    {
        if (! in_array($order->status, ['pending', 'paid'])) return;

        DB::transaction(function () use ($order) {
            $inventory = ProductCatalog::inventory();

            foreach ($order->items as $item) {
                $variant = ProductVariant::find($item->variant_id);

                if ($order->status === 'pending') {
                    $inventory->release($variant, $item->quantity, InventoryReason::ORDER_CANCELLED, $order);
                } else {
                    // Already paid (stock committed) — restock it
                    $inventory->adjust($variant, $item->quantity, InventoryReason::RETURN_ITEM, $order);
                }
            }

            $order->update(['status' => 'cancelled']);
        });
    }
}
```

---

## 4. Internal Catalog (B2B / Internal Product Database)

For internal product databases with cost price, product codes, and custom metadata.

```php
// Query with cost price and product codes
$products = Product::with(['variants', 'brand', 'primaryCategory'])
    ->orderBy('code')
    ->paginate(50);

foreach ($products as $product) {
    foreach ($product->variants as $variant) {
        echo $variant->cost_price;           // internal cost
        echo $variant->meta['barcode'] ?? null;
    }
}

// Filter by product code prefix
Product::where('code', 'LIKE', 'WM-%')->get();

// Export for internal use
$products = Product::select(['id', 'name', 'code', 'status'])
    ->with('variants:id,product_id,sku,price,cost_price')
    ->cursor();
```

---

## 5. Digital & Physical Mixed Catalog

For mixed catalogs: physical variants (tracked stock) + digital variants (unlimited) in the same product.

```php
// Physical variant — tracked stock
$physicalVariant->inventoryItem()->create([
    'quantity' => 50,
    'policy'   => InventoryPolicy::Track,
    'low_stock_threshold' => 5,
]);

// Digital variant — unlimited (same product)
$digitalVariant->inventoryItem()->create([
    'quantity' => 0,
    'policy'   => InventoryPolicy::Allow,  // always available
]);

// Display correctly for mixed product
$badge = $variant->inventoryItem->policy === InventoryPolicy::Allow
    ? 'Unlimited Download'
    : "Stock: {$variant->inventoryItem->availableQuant