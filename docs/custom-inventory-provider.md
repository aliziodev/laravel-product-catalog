# Custom Inventory Provider

By default, this package stores stock in the `catalog_inventory_items` table (the `database` driver). If your application **already has its own stock system** — whether that is your own inventory table, an ERP, a WMS, or a third-party API — you do not need to migrate your data into the package table.

Just implement a single interface, and the package will communicate with your stock system.

---

## When to Use a Custom Driver

| Situation | Solution |
|---|---|
| Stock is managed in your own app table (`inventories`, `stocks`, etc.) | Custom driver — read from your table |
| Stock comes from a company ERP / WMS | Custom driver — call the ERP API |
| Products do not have stock (services, digital, unlimited) | Use the built-in `null` driver or `InventoryPolicy::Allow` — no custom driver needed |
| Stock is simple and there is no external system | Use the built-in `database` driver — no custom driver needed |

---

## Interface Contract

Implement these five methods:

```php
namespace Aliziodev\ProductCatalog\Contracts;

interface InventoryProviderInterface
{
    // Return the current stock quantity
    public function getQuantity(ProductVariant $variant): int;

    // Is the variant purchasable?
    public function isInStock(ProductVariant $variant): bool;

    // Is there enough stock for the requested quantity?
    public function canFulfill(ProductVariant $variant, int $quantity): bool;

    // Increase or decrease stock (positive delta = restock, negative = deduct)
    public function adjust(ProductVariant $variant, int $delta, string $reason = '', ?Model $reference = null): void;

    // Set stock to an absolute value
    public function set(ProductVariant $variant, int $quantity, string $reason = '', ?Model $reference = null): void;
}
```

---

## Example 1 — Your Own Inventory Table

Most common case: your application already has its own `inventories` table and you do not want to duplicate data into the package table.

```php
// Your table schema (example)
// inventories: id, sku, quantity, updated_at
```

```php
<?php

namespace App\Inventory;

use Aliziodev\ProductCatalog\Contracts\InventoryProviderInterface;
use Aliziodev\ProductCatalog\Exceptions\InventoryException;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use App\Models\Inventory; // your own model
use Illuminate\Database\Eloquent\Model;

class AppInventoryProvider implements InventoryProviderInterface
{
    private function find(ProductVariant $variant): ?Inventory
    {
        return Inventory::where('sku', $variant->sku)->first();
    }

    public function getQuantity(ProductVariant $variant): int
    {
        return $this->find($variant)?->quantity ?? 0;
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
        $record = $this->find($variant);

        if (! $record) {
            throw new \RuntimeException("SKU not found in inventory: {$variant->sku}");
        }

        $newQty = $record->quantity + $delta;

        if ($newQty < 0) {
            throw InventoryException::insufficientStock($variant, abs($delta));
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
            ['sku' => $variant->sku],
            ['quantity' => max(0, $quantity)]
        );
    }
}
```

---

## Example 2 — External API (ERP / WMS)

Stock is managed in an external system that exposes a REST API — for example a company ERP or a third-party WMS service.

```php
<?php

namespace App\Inventory;

use Aliziodev\ProductCatalog\Contracts\InventoryProviderInterface;
use Aliziodev\ProductCatalog\Exceptions\InventoryException;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class ErpInventoryProvider implements InventoryProviderInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {}

    public function getQuantity(ProductVariant $variant): int
    {
        $response = Http::withToken($this->apiKey)
            ->get("{$this->baseUrl}/stock/{$variant->sku}");

        if ($response->failed()) {
            return 0; // fail-safe: assume out of stock if the API cannot be reached
        }

        return (int) $response->json('quantity', 0);
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
        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/stock/{$variant->sku}/adjust", [
                'delta'        => $delta,
                'reason'       => $reason,
                'reference_id' => $reference?->getKey(),
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                "Failed to update stock in ERP for SKU: {$variant->sku}"
            );
        }
    }

    public function set(
        ProductVariant $variant,
        int $quantity,
        string $reason = '',
        ?Model $reference = null,
    ): void {
        Http::withToken($this->apiKey)
            ->put("{$this->baseUrl}/stock/{$variant->sku}", [
                'quantity' => max(0, $quantity),
                'reason'   => $reason,
            ]);
    }
}
```

---

## Register the Driver

In `AppServiceProvider` or your own `ServiceProvider`:

```php
use Aliziodev\ProductCatalog\Facades\ProductCatalog;

public function boot(): void
{
    // Driver backed by your app table
    ProductCatalog::extend('app', function ($app) {
        return new \App\Inventory\AppInventoryProvider;
    });

    // Driver backed by an external ERP
    ProductCatalog::extend('erp', function ($app) {
        return new \App\Inventory\ErpInventoryProvider(
            baseUrl: config('services.erp.url'),
            apiKey:  config('services.erp.key'),
        );
    });
}
```

Activate it via `.env`:

```env
PRODUCT_CATALOG_INVENTORY_DRIVER=app
# or
PRODUCT_CATALOG_INVENTORY_DRIVER=erp
```

Switch it per environment:

```env
# local — use the package database table for development
PRODUCT_CATALOG_INVENTORY_DRIVER=database

# production — use the company ERP
PRODUCT_CATALOG_INVENTORY_DRIVER=erp
```

---

## Usage Stays the Same

Once the driver is registered, every facade call stays the same — your application code does not need to know which driver is active:

```php
use Aliziodev\ProductCatalog\Facades\ProductCatalog;

$inventory = ProductCatalog::inventory(); // resolve the active driver

$inventory->isInStock($variant);
$inventory->canFulfill($variant, 10);
$inventory->adjust($variant, -3, 'order_paid', $order);
$inventory->set($variant, 50, 'physical_count');
```

---

## Fallback If the External System Is Down

If your ERP / WMS cannot be reached from time to time, wrap it with a fallback to the built-in database driver:

```php
<?php

namespace App\Inventory;

use Aliziodev\ProductCatalog\Contracts\InventoryProviderInterface;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;

class FallbackInventoryProvider implements InventoryProviderInterface
{
    public function __construct(
        private readonly InventoryProviderInterface $primary,   // ERP / WMS
        private readonly InventoryProviderInterface $fallback,  // built-in database driver
    ) {}

    public function getQuantity(ProductVariant $variant): int
    {
        try {
            return $this->primary->getQuantity($variant);
        } catch (\Throwable) {
            return $this->fallback->getQuantity($variant);
        }
    }

    public function isInStock(ProductVariant $variant): bool
    {
        try {
            return $this->primary->isInStock($variant);
        } catch (\Throwable) {
            return $this->fallback->isInStock($variant);
        }
    }

    public function canFulfill(ProductVariant $variant, int $quantity): bool
    {
        try {
            return $this->primary->canFulfill($variant, $quantity);
        } catch (\Throwable) {
            return $this->fallback->canFulfill($variant, $quantity);
        }
    }

    public function adjust(
        ProductVariant $variant,
        int $delta,
        string $reason = '',
        ?Model $reference = null,
    ): void {
        try {
            $this->primary->adjust($variant, $delta, $reason, $reference);
        } catch (\Throwable) {
            $this->fallback->adjust($variant, $delta, $reason, $reference);
        }
    }

    public function set(
        ProductVariant $variant,
        int $quantity,
        string $reason = '',
        ?Model $reference = null,
    ): void {
        try {
            $this->primary->set($variant, $quantity, $reason, $reference);
        } catch (\Throwable) {
            $this->fallback->set($variant, $quantity, $reason, $reference);
        }
    }
}
```

Register it:

```php
use Aliziodev\ProductCatalog\Inventory\DatabaseInventoryProvider;

ProductCatalog::extend('erp-with-fallback', function ($app) {
    return new \App\Inventory\FallbackInventoryProvider(
        primary:  new \App\Inventory\ErpInventoryProvider(
            baseUrl: config('services.erp.url'),
            apiKey:  config('services.erp.key'),
        ),
        fallback: $app->make(DatabaseInventoryProvider::class),
    );
});
```

---

## Testing Your Driver

```php
// tests/Unit/Inventory/AppInventoryProviderTest.php
use App\Inventory\AppInventoryProvider;
use App\Models\Inventory;
use Aliziodev\ProductCatalog\Models\ProductVariant;

it('returns zero when sku not found in inventory table', function () {
    $variant = ProductVariant::factory()->create(['sku' => 'NOT-EXIST']);

    expect((new AppInventoryProvider)->getQuantity($variant))->toBe(0);
});

it('reports in stock when quantity is positive', function () {
    $variant = ProductVariant::factory()->create(['sku' => 'TS-001']);
    Inventory::create(['sku' => 'TS-001', 'quantity' => 10]);

    expect((new AppInventoryProvider)->isInStock($variant))->toBeTrue();
});

it('throws when adjusting below zero', function () {
    $variant = ProductVariant::factory()->create(['sku' => 'TS-001']);
    Inventory::create(['sku' => 'TS-001', 'quantity' => 3]);

    expect(fn () => (new AppInventoryProvider)->adjust($variant, -5))
        ->toThrow(\Aliziodev\ProductCatalog\Exceptions\InventoryException::class);
});
```

---

## Summary

| | |
|---|---|
| `ProductCatalog::extend($name, $resolver)` | Register a new driver |
| `ProductCatalog::inventory()` | Resolve the active driver |
| `InventoryProviderInterface` | Contract you must implement |
| `InventoryException::insufficientStock($variant, $qty)` | Built-in exception for insufficient stock |
| `PRODUCT_CATALOG_INVENTORY_DRIVER` | `.env` key used to switch drivers |
