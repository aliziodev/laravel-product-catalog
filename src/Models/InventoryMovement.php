<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Models;

use Aliziodev\ProductCatalog\Concerns\HasCatalogTable;
use Aliziodev\ProductCatalog\Database\Factories\InventoryMovementFactory;
use Aliziodev\ProductCatalog\Enums\MovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryMovement extends Model
{
    use HasCatalogTable;
    use HasFactory;

    /** Movements are immutable — no updated_at. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'variant_id',
        'type',
        'delta',
        'quantity_before',
        'quantity_after',
        'reserved_before',
        'reserved_after',
        'reason',
        'referenceable_type',
        'referenceable_id',
    ];

    protected $casts = [
        'type' => MovementType::class,
        'delta' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
        'reserved_before' => 'integer',
        'reserved_after' => 'integer',
    ];

    protected static function newFactory(): InventoryMovementFactory
    {
        return InventoryMovementFactory::new();
    }

    protected function getCatalogTableSuffix(): string
    {
        return 'inventory_movements';
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /** The model that caused this movement (Order, PurchaseOrder, etc.). */
    public function referenceable(): MorphTo
    {
        return $this->morphTo();
    }

    // -------------------------------------------------------------------------
    // Quantity helpers
    // -------------------------------------------------------------------------

    /** True when total stock quantity increased (delta > 0 on stock movements). */
    public function isInbound(): bool
    {
        return $this->delta > 0;
    }

    /** True when total stock quantity decreased (delta < 0 on stock movements). */
    public function isOutbound(): bool
    {
        return $this->delta < 0;
    }

    // -------------------------------------------------------------------------
    // Reservation helpers
    // -------------------------------------------------------------------------

    /** True for Reserve and Release movements (reserved_quantity changed). */
    public function isReservationMovement(): bool
    {
        return $this->type === MovementType::Reserve || $this->type === MovementType::Release;
    }

    /** True when reserved_before/after columns are populated. */
    public function affectsReservation(): bool
    {
        return $this->reserved_before !== null;
    }
}
