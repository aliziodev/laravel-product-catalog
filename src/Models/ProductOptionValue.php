<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Models;

use Aliziodev\ProductCatalog\Concerns\HasCatalogTable;
use Aliziodev\ProductCatalog\Database\Factories\ProductOptionValueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductOptionValue extends Model
{
    use HasCatalogTable;
    use HasFactory;

    protected $fillable = [
        'option_id',
        'value',
        'position',
    ];

    protected static function newFactory(): ProductOptionValueFactory
    {
        return ProductOptionValueFactory::new();
    }

    protected function getCatalogTableSuffix(): string
    {
        return 'product_option_values';
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'option_id');
    }

    public function variants(): BelongsToMany
    {
        $prefix = config('product-catalog.table_prefix', 'catalog_');

        return $this->belongsToMany(
            ProductVariant::class,
            $prefix.'variant_option_values',
            'option_value_id',
            'variant_id'
        );
    }
}
