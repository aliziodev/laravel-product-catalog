<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'description' => $this->description,
            'position' => $this->position,
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
