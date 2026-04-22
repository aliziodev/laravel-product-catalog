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
    const UPDATED_AT = null;

    protected $fillable = [
        'variant_id',
        'type',
        'delta',
        'quantity_before',
        'quantity_after',
        'reason',
        'referenceable_type',
        'referenceable_id',
    ];

    protected $casts = [
        'type' => MovementType::class,
        'delta' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
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

    public function isInbound(): bool
    {
        return $this->delta > 0;
    }

    public function isOutbound(): bool
    {
        return $this->delta < 0;
    }
}
