<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Models;

use Aliziodev\ProductCatalog\Concerns\HasCatalogTable;
use Aliziodev\ProductCatalog\Concerns\HasSlug;
use Aliziodev\ProductCatalog\Database\Factories\ProductFactory;
use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Aliziodev\ProductCatalog\Enums\ProductStatus;
use Aliziodev\ProductCatalog\Enums\ProductType;
use Aliziodev\ProductCatalog\Events\ProductArchived;
use Aliziodev\ProductCatalog\Events\ProductPublished;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasCatalogTable;
    use HasFactory;
    use HasSlug;
    use SoftDeletes;

    protected $fillable = [
        'brand_id',
        'primary_category_id',
        'name',
        'code',
        'slug',
        'route_key',
        'description',
        'short_description',
        'type',
        'status',
        'featured_image_path',
        'meta_title',
        'meta_description',
        'meta',
        'published_at',
    ];

    protected $casts = [
        'brand_id' => 'integer',
        'primary_category_id' => 'integer',
        'type' => ProductType::class,
        'status' => ProductStatus::class,
        'published_at' => 'datetime',
        'meta' => 'array',
    ];

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    protected function getCatalogTableSuffix(): string
    {
        return 'products';
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('position');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    public function categories(): BelongsToMany
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        return $this->belongsToMany(
            Category::class,
            $prefix.'product_categories',
            'product_id',
            'category_id'
        );
    }

    public function tags(): BelongsToMany
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        return $this->belongsToMany(
            Tag::class,
            $prefix.'product_tags',
            'product_id',
            'tag_id'
        );
    }

    // -------------------------------------------------------------------------
    // State transitions
    // -------------------------------------------------------------------------

    public function publish(): void
    {
        $this->update([
            'status' => ProductStatus::Published,
            'published_at' => $this->published_at ?? now(),
        ]);

        event(new ProductPublished($this));
    }

    public function archive(): void
    {
        $this->update(['status' => ProductStatus::Archived]);

        event(new ProductArchived($this));
    }

    public function unpublish(): void
    {
        $this->update(['status' => ProductStatus::Draft]);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProductStatus::Published);
    }

    public function scopeDraft(Builder $query): void
    {
        $query->where('status', ProductStatus::Draft);
    }

    public function scopeArchived(Builder $query): void
    {
        $query->where('status', ProductStatus::Archived);
    }

    public function scopeForBrand(Builder $query, int|Brand $brand): void
    {
        $query->where('brand_id', $brand instanceof Brand ? $brand->getKey() : $brand);
    }

    public function scopeWithTag(Builder $query, int|Tag $tag): void
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');
        $tagId = $tag instanceof Tag ? $tag->getKey() : $tag;

        $query->whereExists(function ($sub) use ($prefix, $tagId) {
            $sub->from($prefix.'product_tags')
                ->whereColumn($prefix.'product_tags.product_id', $prefix.'products.id')
                ->where($prefix.'product_tags.tag_id', $tagId);
        });
    }

    public function scopeSearch(Builder $query, string $term): void
    {
        $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('short_description', 'like', "%{$term}%")
                ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', 'like', "%{$term}%"));
        });
    }

    public function scopeInStock(Builder $query): void
    {
        $query->whereHas('variants', function (Builder $v) {
            $v->where('is_active', true)
                ->whereHas('inventoryItem', function (Builder $i) {
                    $i->where('policy', InventoryPolicy::Allow->value)
                        ->orWhere(function (Builder $q) {
                            $q->where('policy', InventoryPolicy::Track->value)
                                ->whereColumn('quantity', '>', 'reserved_quantity');
                        });
                });
        });
    }

    // -------------------------------------------------------------------------
    // State checks
    // -------------------------------------------------------------------------

    public function isPublished(): bool
    {
        return $this->status === ProductStatus::Published;
    }

    public function isDraft(): bool
    {
        return $this->status === ProductStatus::Draft;
    }

    public function isArchived(): bool
    {
        return $this->status === ProductStatus::Archived;
    }

    public function isVariable(): bool
    {
        return $this->type === ProductType::Variable;
    }

    public function isSimple(): bool
    {
        return $this->type === ProductType::Simple;
    }

    // -------------------------------------------------------------------------
    // Pricing helpers
    // -------------------------------------------------------------------------

    /**
     * Lowest active variant price.
     *
     * Uses the in-memory variants collection when already eager-loaded (zero extra
     * queries). Falls back to a single aggregate DB query when not loaded.
     */
    public function minPrice(): ?float
    {
        if ($this->relationLoaded('variants')) {
            $min = $this->variants->where('is_active', true)->min('price');

            return $min !== null ? (float) $min : null;
        }

        $value = $this->variants()->active()->min('price');

        return $value !== null ? (float) $value : null;
    }

    /**
     * Highest active variant price.
     *
     * Uses the in-memory variants collection when already eager-loaded (zero extra
     * queries). Falls back to a single aggregate DB query when not loaded.
     */
    public function maxPrice(): ?float
    {
        if ($this->relationLoaded('variants')) {
            $max = $this->variants->where('is_active', true)->max('price');

            return $max !== null ? (float) $max : null;
        }

        $value = $this->variants()->active()->max('price');

        return $value !== null ? (float) $value : null;
    }

    /** @return array{min: float, max: float}|null */
    public function priceRange(): ?array
    {
        $min = $this->minPrice();
        $max = $this->maxPrice();

        if ($min === null) {
            return null;
        }

        return ['min' => $min, 'max' => $max];
    }

    // -------------------------------------------------------------------------
    // SKU helpers
    // -------------------------------------------------------------------------

    /**
     * Build a suggested SKU for a variant based on this product's code
     * and the variant's already-loaded option values.
     *
     * Call this AFTER attaching option values to the variant:
     *
     *   $variant->optionValues()->sync($valueIds);
     *   $variant->load('optionValues');
     *   $variant->update(['sku' => $product->buildVariantSku($variant)]);
     *
     * The result is not saved automatically — the caller decides whether to use
     * the suggestion as-is or modify it first.
     */
    public function buildVariantSku(ProductVariant $variant): string
    {
        $base = $this->code
            ? strtoupper($this->code)
            : strtoupper(Str::slug($this->name, '-'));

        $suffix = $variant->optionValues
            ->pluck('value')
            ->map(fn (string $v): string => strtoupper(Str::slug($v, '-')))
            ->filter()
            ->join('-');

        return $suffix !== '' ? "{$base}-{$suffix}" : $base;
    }
}
