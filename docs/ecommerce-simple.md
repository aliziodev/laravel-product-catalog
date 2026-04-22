# Use Case: Simple Ecommerce

Minimal end-to-end ecommerce setup — from catalog to order placement — using this package for the product/inventory layer. You define your own `Order` and `OrderItem` models; this guide shows how to wire them to the catalog.

---

## What You'll Build

- Product and variant data for the storefront
- 3-state order flow: **pending → paid → shipped** (or cancelled)
- Soft-reserve on order creation, actual deduction on payment confirmation
- Stock restoration on cancellation or payment failure
- Movement history for audit trail

---

## The 3-State Inventory Flow

This is the most important concept to get right. Never deduct stock at cart creation — deduct at the last responsible moment.

```
[Customer checks out]
        │
        ▼
  reserve($qty)          ← order created, awaiting payment
  status: pending           quantity unchanged, reserved_quantity increases
        │
   ┌────┴────┐
   │         │
payment    payment
 failed    confirmed
   │         │
   ▼         ▼
release($qty)        adjust(-$qty)    ← actual deduction
status: cancelled    status: paid        quantity decreases
                          │
                          ▼
                    shipped → status: completed
                    (no further inventory change)
```

---

## 1. Recommended App Schema

Create your own order tables (outside this package):

```php
// database/migrations/xxxx_create_orders_table.php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('status')->default('pending'); // pending|paid|shipped|completed|cancelled
    $table->decimal('total', 14, 2);
    $table->timestamps();
});

Schema::create('order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained()->cascadeOnDelete();
    $table->unsignedBigInteger('variant_id'); // references catalog_product_variants.id
    $table->string('sku');
    $table->string('product_name');
    $table->string('option_label')->nullable();
    $table->unsignedInteger('quantity');
    $table->decimal('unit_price', 14, 2); // always snapshot at order time
    $table->timestamps();
});
```

---

## 2. State 1 — Order Created (Pending Payment)

Validate stock, create the order, and **soft-reserve** quantities. The actual `quantity` column is not touched yet — only `reserved_quantity` increases.

```php
use Aliziodev\ProductCatalog\Facades\ProductCatalog;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class CreateOrderAction
{
    public function execute(array $items, int $userId): Order
    {
        return DB::transaction(function () use ($items, $userId) {
            $inventory = ProductCatalog::inventory();
            $total     = 0;

            // 1. Validate available stock before touching anything
            foreach ($items as $item) {
                $variant = ProductVariant::with('inventoryItem')->findOrFail($item['variant_id']);

                if (! $inventory->canFulfill($variant, $item['quantity'])) {
                    throw new \Exception("Insufficient stock for SKU: {$variant->sku}");
                }
            }

            // 2. Create order header
            $order = Order::create([
                'user_id' => $userId,
                'status'  => 'pending',
                'total'   => 0,
            ]);

            // 3. Create line items and soft-reserve stock
            foreach ($items as $item) {
                $variant = ProductVariant::with(['product', 'inventoryItem', 'optionValues'])->find($item['variant_id']);
                $lineTotal = (float) $variant->price * $item['quantity'];

                $order->items()->create([
                    'variant_id'   => $variant->id,
                    'sku'          => $variant->sku,
                    'product_name' => $variant->product->name,
                    'option_label' => $variant->displayName(), // "Red / 42" — snapshot now
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $variant->price,         // snapshot price at purchase moment
                ]);

                // Soft-reserve: reserved_quantity++ but quantity unchanged
                $variant->inventoryItem->reserve($item['quantity']);

                $total += $lineTotal;
            }

            $order->update(['total' => $total]);

            return $order; // status: pending, waiting for payment
        });
    }
}
```

---

## 3. State 2A — Payment Confirmed (Order Paid)

When payment succeeds, perform the actual stock deduction. The reservation is converted to a real deduction.

```php
class ConfirmPaymentAction
{
    public function execute(Order $order): void
    {
        if ($order->status !== 'pending') {
            return;
        }

        DB::transaction(function () use ($order) {
            $inventory = ProductCatalog::inventory();

            foreach ($order->items as $item) {
                $variant = ProductVariant::with('inventoryItem')->find($item->variant_id);

                if (! $variant) {
                    continue;
                }

                // Release the soft-reserve first
                $variant->inventoryItem->release($item->quantity);

                // Then perform the actual stock deduction with audit trail
                $inventory->adjust($variant, -$item->quantity, 'order_paid', $order);
            }

            $order->update(['status' => 'paid']);
        });
    }
}
```

---

## 4. State 2B — Payment Failed or Order Cancelled

Release the soft-reserve. `quantity` stays the same — only `reserved_quantity` decreases back.

```php
class CancelOrderAction
{
    public function execute(Order $order): void
    {
        if (! in_array($order->status, ['pending', 'paid'])) {
            return;
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $variant = ProductVariant::with('inventoryItem')->find($item->variant_id);

                if (! $variant?->inventoryItem) {
                    continue;
                }

                if ($order->status === 'pending') {
                    // Payment never went through — just release the reservation
                    $variant->inventoryItem->release($item->quantity);
                } else {
                    // Order was already paid and stock was deducted — restock it
                    ProductCatalog::inventory()->adjust(
                        $variant,
                        $item->quantity,
                        'order_cancelled',
                        $order
                    );
                }
            }

            $order->update(['status' => 'cancelled']);
        });
    }
}
```

---

## 5. Restock After Return

```php
class ProcessReturnAction
{
    public function execute(Order $order, int $variantId, int $qty): void
    {
        $variant = ProductVariant::findOrFail($variantId);

        ProductCatalog::inventory()->adjust(
            $variant,
            $qty,
            'customer_return',
            $order
        );
    }
}
```

---

## 6. View Movement History

Every `adjust()` and `set()` via `DatabaseInventoryProvider` writes an `InventoryMovement` record. `reserve()` and `release()` are lightweight column increments — they do **not** write movements (they are transient, not permanent stock changes).

```php
use Aliziodev\ProductCatalog\Models\InventoryMovement;

$movements = InventoryMovement::where('variant_id', $variant->id)
    ->latest()
    ->get();

foreach ($movements as $m) {
    // $m->delta         — positive (restock) or negative (deduction)
    // $m->reason        — 'order_paid', 'order_cancelled', 'customer_return', etc.
    // $m->referenceable — polymorphic: the Order model (if passed as $reference)
}
```

---

## 7. Price Snapshot

Prices change over time. Always capture the unit price at order creation inside `order_items.unit_price`. Never re-read `$variant->price` for historical order display.

```php
// ✓ Correct — captured at create time
'unit_price' => $variant->price,

// ❌ Wrong — price may have changed since order was placed
$order->items->sum(fn ($i) => ProductVariant::find($i->variant_id)->price * $i->quantity);
```

---

## Relevant Model API

| Method | When to use |
|---|---|
| `$inventory->canFulfill($variant, $qty)` | Before creating the order — pre-purchase check |
| `$item->reserve($qty)` | Order created (pending payment) — soft-reserve |
| `$item->release($qty)` | Payment failed or order cancelled while pending |
| `$inventory->adjust($variant, -$qty, 'order_paid', $order)` | Payment confirmed — actual deduction |
| `$inventory->adjust($variant, +$qty, 'order_cancelled', $order)` | Cancelled after payment — restock |
| `$inventory->adjust($variant, +$qty, 'customer_return', $order)` | Return after shipment |
| `$variant->displayName()` | Snapshot option label ("Red / 42") at order time |
| `InventoryMovement` | Append-only audit log written by `adjust()` and `set()` |
