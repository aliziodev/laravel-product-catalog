<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Models;

use Aliziodev\ProductCatalog\Concerns\HasCatalogTable;
use Aliziodev\ProductCatalog\Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasCatalogTable;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'position',
    ];

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    protected function getCatalogTableSuffix(): string
    {
        return 'categories';
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('position');
    }

    public function products(): BelongsToMany
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        return $this->belongsToMany(
            Product::class,
            $prefix.'product_categories',
            'category_id',
            'product_id'
        );
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }
}
