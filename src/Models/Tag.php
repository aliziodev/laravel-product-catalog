<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Models;

use Aliziodev\ProductCatalog\Concerns\HasCatalogTable;
use Aliziodev\ProductCatalog\Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tag extends Model
{
    use HasCatalogTable;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function newFactory(): TagFactory
    {
        return TagFactory::new();
    }

    protected function getCatalogTableSuffix(): string
    {
        return 'tags';
    }

    public function products(): BelongsToMany
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        return $this->belongsToMany(
            Product::class,
            $prefix.'product_tags',
            'tag_id',
            'product_id'
        );
    }
}
