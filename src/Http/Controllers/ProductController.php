<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Http\Controllers;

use Aliziodev\ProductCatalog\Http\Resources\ProductResource;
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Search\ProductSearchBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        // Laravel ignores a negative limit, so per_page=-1 used to return the whole
        // catalog in one response. Anything below 1 now falls back to the default.
        $perPage = (int) $request->input('per_page', 15);
        $perPage = $perPage < 1 ? 15 : min($perPage, 50);
        $page = max(1, (int) $request->input('page', 1));

        $paginator = ProductSearchBuilder::fromRequest($request)
            ->withRelations(['brand', 'primaryCategory', 'tags'])
            ->paginate($perPage, $page);

        return ProductResource::collection($paginator);
    }

    public function show(string $slug): ProductResource
    {
        /** @var class-string<Product> $modelClass */
        $modelClass = config('product-catalog.model', Product::class);

        $product = $modelClass::published()
            ->with(['brand', 'primaryCategory', 'tags', 'variants.inventoryItem', 'options.values'])
            ->bySlug($slug)
            ->firstOrFail();

        return new ProductResource($product);
    }
}
