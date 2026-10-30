<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Models;

use Aliziodev\ProductCatalog\Concerns\HasCatalogTable;
use Aliziodev\ProductCatalog\Database\Factories\InventoryItemFactory;
use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Aliziodev\ProductCatalog\Exceptions\InventoryException;
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

    /**
     * Items whose available quantity (quantity − reserved) is at or below the threshold.
     *
     * Written as an addition rather than `quantity - reserved_quantity` because the
     * columns are UNSIGNED: on MySQL the subtraction raises error 1690 for the whole
     * query as soon as any row has reserved_quantity > quantity.
     */
    public function scopeLowStock(Builder $query): void
    {
        $query->whereNotNull('low_stock_threshold')
            ->whereRaw('quantity <= reserved_quantity + low_stock_threshold');
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

    /**
     * Low-level counter update. It does not lock the row, check availability or
     * record a movement — use ProductCatalog::inventory()->reserve() instead.
     *
     * @throws InventoryException when $qty is negative
     */
    public function reserve(int $qty): void
    {
        if ($qty < 0) {
            throw InventoryException::invalidQuantity($qty);
        }

        $this->increment('reserved_quantity', $qty);
    }

    /**
     * Low-level counter update, capped at the current reserved quantity.
     * Prefer ProductCatalog::inventory()->release().
     *
     * @throws InventoryException when $qty is negative
     */
    public function release(int $qty): void
    {
        if ($qty < 0) {
            throw InventoryException::invalidQuantity($qty);
        }

        $this->decrement('reserved_quantity', min($qty, $this->reserved_quantity));
    }
}
