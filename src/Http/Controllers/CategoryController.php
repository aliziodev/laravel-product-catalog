<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Http\Controllers;

use Aliziodev\ProductCatalog\Http\Resources\CategoryResource;
use Aliziodev\ProductCatalog\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::roots()
            ->with('children')
            ->orderBy('position')
            ->get();

        return CategoryResource::collection($categories);
    }
}
