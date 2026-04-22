# Custom Inventory Driver — Reference

Read this file when the user needs a custom inventory driver (own table, ERP, WMS, Redis, fallback strategy).

---

## When to Use a Custom Driver vs Built-in

| Situation | Solution |
|-----------|----------|
| Stock in your own app table (`inventories`, `stocks`, etc.) | Custom driver |
| Stock from a company ERP / WMS | Custom driver |
| Digital products / unlimited stock | Built-in `null` driver or `InventoryPolicy::Allow` — no custom driver needed |
| Simple stock, no external system | Built-in `database` driver (default) — no custom driver needed |

---

## Interface Contract (5 required methods)

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

    // Increase or decrease stock (positive = restock, negative = deduct)
    public function adjust(ProductVariant $variant, int $delta, string $reason = '', ?Model $reference = null): void;

    // Set stock to an absolute value
    public function set(ProductVariant $variant, int $quantity, string $reason = '', ?Model $reference = null): void;
}
```

---

## Example 1 — Your Own Inventory Table

Most common case: your app already has its own `inventories` table and you don't want to duplicate data.

```php
// Your table schema (example)
// inventories: id, sku, quantity, updated_at

namespace App\Inventory;

use Aliziodev\ProductCatalog\Contracts\InventoryProviderInterface;
use Aliziodev\ProductCatalog\Exceptions\InventoryException;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use App\Models\Inventory;
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
        $record = $this->find($variant)
            ?? throw new \RuntimeException("SKU not found in inventory: {$variant->sku}");

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
            ['sku'      => $variant->sku],
            ['quantity' => max(0, $quantity)]
        );
    }
}
```

---

## Example 2 — External API (ERP / WMS)

Stock is managed in an external system that exposes a REST API.

```php
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

        return $response->failed() ? 0 : (int) $response->json('quantity', 0);
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
            throw new \RuntimeException("ERP update failed for SKU: {$variant->sku}");
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

## Example 3 — Fallback Strategy (ERP + DB fallback)

Useful when the ERP/WMS is occasionally unreachable:

```php
namespace App\Inventory;

use Aliziodev\ProductCatalog\Contracts\InventoryProviderInterface;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;

class FallbackInventoryProvider implements InventoryProviderInterface
{
    public function __construct(
        private readonly InventoryProviderInterface $primary,   // ERP/WMS
        private readonly InventoryProviderInterface $fallback,  // database driver
    ) {}

    public function getQuantity(ProductVariant $variant): int
    {
        try { return $this->primary->getQuantity($variant); }
        catch (\Throwable) { return $this->fallback->getQuantity($variant); }
    }

    public function isInStock(ProductVariant $variant): bool
    {
        try { return $this->primary->isInStock($variant); }
        catch (\Throwable) { return $this->fallback->isInStock($variant); }
    }

    public function canFulfill(ProductVariant $variant, int $quantity): bool
    {
        try { return $this->primary->canFulfill($variant, $quantity); }
        catch (\Throwable) { return $this->fallback->canFulfill($variant, $quantity); }
    }

    public function adjust(ProductVariant $variant, int $delta, string $reason = '', ?Model $reference = null): void
    {
        try { $this->primary->adjust($variant, $delta, $reason, $reference); }
        catch (\Throwable) { $this->fallback->adjust($variant, $delta, $reason, $reference); }
    }

    public function set(ProductVariant $variant, int $quantity, string $reason = '', ?Model $reference = null): void
    {
        try { $this->primary->set($variant, $quantity, $reason, $reference); }
        catch (\Throwable) { $this->fallback->set($variant, $quantity, $reason, $reference); }
    }
}
```

---

## Registering Drivers

```php
// AppServiceProvider or a dedicated ServiceProvider
use Aliziodev\ProductCatalog\Facades\ProductCatalog;
use Aliziodev\ProductCatalog\Inventory\DatabaseInventoryProvider;

public function boot(): void
{
    // Driver backed by your own table
    ProductCatalog::extend('app', fn ($app) => new \App\Inventory\AppInventoryProvider);

    // Driver backed by external ERP
    ProductCatalog::extend('erp', fn ($app) => new \App\Inventory\ErpInventoryProvider(
        baseUrl: config('services.erp.url'),
        apiKey:  config('services.erp.key'),
    ));

    // ERP with DB fallback
    ProductCatalog::extend('erp-with-fallback', function ($app) {
        return new \App\Inventory\FallbackInventoryProvider(
            primary:  new \App\Inventory\ErpInventoryProvider(
                baseUrl: config('services.erp.url'),
                apiKey:  config('services.erp.key'),
            ),
            fallback: $app->make(DatabaseInventoryProvider::class),
        );
    });
}
```

Activate via `.env`:
```env
PRODUCT_CATALOG_INVENTORY_DRIVER=app
# or: erp, erp-with-fallback
```

---

## Usage Stays the Same (Driver-Agnostic)

```php
$inventory = ProductCatalog::inventory(); // resolves the active driver

