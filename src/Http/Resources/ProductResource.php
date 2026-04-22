<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'featured_image_path' => $this->featured_image_path,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta' => $this->meta,
            'published_at' => $this->published_at?->toISOString(),
            'brand' => BrandResource::make($this->whenLoaded('brand')),
            'primary_category' => CategoryResource::make($this->whenLoaded('primaryCategory')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
        ];
    }
}
