<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Models;

use Aliziodev\ProductCatalog\Concerns\HasCatalogTable;
use Aliziodev\ProductCatalog\Concerns\UsesProductModel;
use Aliziodev\ProductCatalog\Database\Factories\ProductOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOption extends Model
{
    use HasCatalogTable;
    use HasFactory;
    use UsesProductModel;

    protected $fillable = [
        'product_id',
        'name',
        'position',
    ];

    protected static function newFactory(): ProductOptionFactory
    {
        return ProductOptionFactory::new();
    }

    protected function getCatalogTableSuffix(): string
    {
        return 'product_options';
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo($this->productModel(), 'product_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductOptionValue::class, 'option_id')->orderBy('position');
    }
}
