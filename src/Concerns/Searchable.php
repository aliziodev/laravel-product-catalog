<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Provides a sensible toSearchableArray() for the Product model when using
 * Laravel Scout (ScoutSearchDriver).
 *
 * Usage — in your application's extended Product model:
 *
 *   use Laravel\Scout\Searchable as ScoutSearchable;
 *   use Aliziodev\ProductCatalog\Concerns\Searchable;
 *
 *   class Product extends \Aliziodev\ProductCatalog\Models\Product
 *   {
 *       use ScoutSearchable, Searchable {
 *           Searchable::toSearchableArray insteadof ScoutSearchable;
 *           Searchable::makeAllSearchableUsing insteadof ScoutSearchable;
 *       }
 *   }
 *
 * Both traits define these two methods, so the insteadof block is required:
 * without it PHP stops with a fatal trait collision error. Keep this trait to
 * these two methods; any other method Scout also defines would collide again.
 *
 * Then run: php artisan scout:import "App\Models\Product"
 */
trait Searchable
{
    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        // Scout indexes a single model on every save without its relations.
        // Load what is missing so the index never loses brand, category, tag,
        // SKU and price data. Bulk imports already eager-load these through
        // makeAllSearchableUsing(), so this adds no queries there.
        if ($this->exists) {
            $this->loadMissing(['brand', 'primaryCategory', 'categories', 'tags', 'variants']);
        }

        $minPrice = null;

        if ($this->relationLoaded('variants')) {
            $minPrice = $this->variants->where('is_active', true)->min('price');
            $minPrice = $minPrice !== null ? (float) $minPrice : null;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'type' => $this->type?->value,
            'status' => $this->status?->value,

            // Brand
            'brand_name' => $this->relationLoaded('brand')
                ? $this->brand?->name
                : null,

            // Primary category — used for faceting / single-value category filter
            'primary_category_name' => $this->relationLoaded('primaryCategory')
                ? $this->primaryCategory?->name
                : null,
            'primary_category_slug' => $this->relationLoaded('primaryCategory')
                ? $this->primaryCategory?->slug
                : null,

            // All assigned categories — used for multi-value category filter
            'categories' => $this->relationLoaded('categories')
                ? $this->categories->pluck('name')->all()
                : [],
            'category_slugs' => $this->relationLoaded('categories')
                ? $this->categories->pluck('slug')->all()
                : [],

            // Tags
            'tags' => $this->relationLoaded('tags')
                ? $this->tags->pluck('slug')->all()
                : [],

            // Variants
            'skus' => $this->relationLoaded('variants')
                ? $this->variants->pluck('sku')->filter()->values()->all()
                : [],
            'min_price' => $minPrice,

            'published_at' => $this->published_at?->toISOString(),
        ];
    }

    /**
     * Eager-load relations when bulk-indexing via scout:import.
     */
    public function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with(['brand', 'primaryCategory', 'categories', 'tags', 'variants']);
    }
}
