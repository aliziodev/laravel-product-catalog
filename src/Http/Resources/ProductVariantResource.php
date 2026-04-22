<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'price' => (float) $this->price,
            'compare_price' => $this->compare_price !== null ? (float) $this->compare_price : null,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'is_on_sale' => $this->isOnSale(),
            'discount_percentage' => $this->discountPercentage(),
            'weight' => $this->weight !== null ? (float) $this->weight : null,
            'length' => $this->length !== null ? (float) $this->length : null,
            'width' => $this->width !== null ? (float) $this->width : null,
            'height' => $this->height !== null ? (float) $this->height : null,
            'position' => $this->position,
            'meta' => $this->meta,
        ];
    }
}
