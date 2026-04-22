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
        $perPage = min((int) $request->input('per_page', 15), 50);
        $page = (int) $request->input('page', 1);

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
