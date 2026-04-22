<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Http\Controllers;

use Aliziodev\ProductCatalog\Http\Resources\ProductResource;
use Aliziodev\ProductCatalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()
            ->published()
            ->with(['brand', 'primaryCategory', 'tags']);

        if ($request->filled('brand')) {
            $query->forBrand((int) $request->brand);
        }

        if ($request->filled('category')) {
            $categoryId = (int) $request->category;
            $query->where(function ($q) use ($categoryId) {
                $q->where('primary_category_id', $categoryId)
                    ->orWhereHas('categories', fn ($sub) => $sub->where('id', $categoryId));
            });
        }

        if ($request->filled('tag')) {
            $query->withTag((int) $request->tag);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $perPage = min((int) $request->input('per_page', 15), 50);

        return ProductResource::collection($query->paginate($perPage));
    }

    public function show(string $slug): ProductResource
    {
        $product = Product::published()
            ->with(['brand', 'primaryCategory', 'tags', 'variants', 'options.values'])
            ->bySlug($slug)
            ->firstOrFail();

        return new ProductResource($product);
    }
}
