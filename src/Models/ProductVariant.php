<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Models;

use Aliziodev\ProductCatalog\Concerns\HasCatalogTable;
use Aliziodev\ProductCatalog\Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductVariant extends Model
{
    use HasCatalogTable;
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'compare_price',
        'cost_price',
        'weight',
        'length',
        'width',
        'height',
        'is_default',
        'is_active',
        'position',
        'meta',
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'compare_price' => 'decimal:4',
        'cost_price' => 'decimal:4',
        'weight' => 'decimal:3',
        'length' => 'decimal:2',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'position' => 'integer',
        'meta' => 'array',
    ];

    protected static function newFactory(): ProductVariantFactory
    {
        return ProductVariantFactory::new();
    }

    protected function getCatalogTableSuffix(): string
    {
        return 'product_variants';
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function optionValues(): BelongsToMany
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        return $this->belongsToMany(
            ProductOptionValue::class,
            $prefix.'variant_option_values',
            'variant_id',
            'option_value_id'
        );
    }

    public function inventoryItem(): HasOne
    {
        return $this->hasOne(InventoryItem::class, 'variant_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'variant_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): void
    {
        $query->where('is_default', true);
    }

    // -------------------------------------------------------------------------
    // Pricing helpers
    // -------------------------------------------------------------------------

    public function isOnSale(): bool
    {
        return $this->compare_price !== null
            && (float) $this->compare_price > (float) $this->price;
    }

    public function discountPercentage(): ?int
    {
        if (! $this->isOnSale()) {
            return null;
        }

        return (int) round(
            ((float) $this->compare_price - (float) $this->price)
            / (float) $this->compare_price * 100
        );
    }

    /**
     * Human-readable label derived from attached option values.
     * Example: "Red / XL"
     */
    public function displayName(): string
    {
        $values = $this->optionValues->pluck('value');

        return $values->isNotEmpty()
            ? $values->join(' / ')
            : ($this->sku ?? "Variant #{$this->id}");
    }
}
