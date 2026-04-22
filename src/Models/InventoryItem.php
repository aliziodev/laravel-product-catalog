<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Models;

use Aliziodev\ProductCatalog\Concerns\HasCatalogTable;
use Aliziodev\ProductCatalog\Database\Factories\InventoryItemFactory;
use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasCatalogTable;
    use HasFactory;

    protected $fillable = [
        'variant_id',
        'quantity',
        'reserved_quantity',
        'low_stock_threshold',
        'policy',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'policy' => InventoryPolicy::class,
    ];

    protected static function newFactory(): InventoryItemFactory
    {
        return InventoryItemFactory::new();
    }

    protected function getCatalogTableSuffix(): string
    {
        return 'inventory_items';
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'variant_id', 'variant_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeLowStock(Builder $query): void
    {
        $query->whereNotNull('low_stock_threshold')
            ->whereRaw('(quantity - reserved_quantity) <= low_stock_threshold');
    }

    // -------------------------------------------------------------------------
    // State checks
    // -------------------------------------------------------------------------

    public function isTracked(): bool
    {
        return $this->policy === InventoryPolicy::Track;
    }

    public function isPurchaseAllowed(): bool
    {
        return $this->policy !== InventoryPolicy::Deny;
    }

    public function isLowStock(): bool
    {
        return $this->low_stock_threshold !== null
            && $this->availableQuantity() <= $this->low_stock_threshold;
    }

    // -------------------------------------------------------------------------
    // Quantity helpers
    // -------------------------------------------------------------------------

    public function availableQuantity(): int
    {
        return max(0, $this->quantity - $this->reserved_quantity);
    }

    public function reserve(int $qty): void
    {
        $this->increment('reserved_quantity', $qty);
    }

    public function release(int $qty): void
    {
        $this->decrement('reserved_quantity', min($qty, $this->reserved_quantity));
    }
}
