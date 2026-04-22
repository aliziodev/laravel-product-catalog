<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Http\Controllers;

use Aliziodev\ProductCatalog\Http\Resources\BrandResource;
use Aliziodev\ProductCatalog\Models\Brand;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class BrandController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BrandResource::collection(Brand::orderBy('name')->get());
    }
}
