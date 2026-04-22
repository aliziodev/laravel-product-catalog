# Use Case: Product Catalog

A read-only product catalog — filterable, searchable, and routable. Suitable for marketing sites, brand showrooms, and any application that displays products without a direct checkout flow.

---

## What You'll Build

- Public catalog routes with slug-based product pages
- Filtering by brand, category, tag, and in-stock status
- Full-text search across name, code, description, and SKU
- Price range display per product
- SEO-friendly slugs that survive product renames

---

## 1. Enable the Built-in Routes

```php
// config/product-catalog.php
'routes' => [
    'enabled'    => true,
    'prefix'     => 'catalog',   // GET /catalog/products, GET /catalog/products/{slug}
    'middleware' => ['api'],
],
```

Or register your own controller:

```php
// routes/web.php
use App\Http\Controllers\CatalogController;

Route::get('/products',       [CatalogController::class, 'index']);
Route::get('/products/{slug}', [CatalogController::class, 'show']);
```

## 2. Index — Filterable Product List

```php
<?php

namespace App\Http\Controllers;

use Aliziodev\ProductCatalog\Http\Resources\ProductResource;
use Aliziodev\ProductCatalog\Models\Brand;
use Aliziodev\ProductCatalog\Models\Category;
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::published()
            ->with(['brand', 'primaryCategory', 'variants' => fn ($q) => $q->active()]);

        if ($request->filled('q')) {
            $query->search($request->string('q')->toString());
        }

        if ($request->filled('brand')) {
            $brand = Brand::where('slug', $request->brand)->firstOrFail();
            $query->forBrand($brand);
        }

        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->firstOrFail();
            $query->where('primary_category_id', $category->id);
        }

        if ($request->filled('tag')) {
            $tag = Tag::where('slug', $request->tag)->firstOrFail();
            $query->withTag($tag);
        }

        if ($request->boolean('in_stock')) {
            $query->inStock();
        }

        $products = $query->latest()->paginate(24);

        return ProductResource::collection($products);
    }

    public function show(string $slug): ProductResource
    {
        $product = Product::published()
            ->with(['brand', 'primaryCategory', 'tags', 'variants.optionValues', 'options.values'])
            ->bySlug($slug)
            ->firstOrFail();

        return ProductResource::make($product);
    }
}
```

## 3. Price Range in the Response

Add price range to `ProductResource` or a dedicated transformer:

```php
// app/Http/Resources/CatalogProductResource.php
use Aliziodev\ProductCatalog\Http\Resources\ProductResource as BaseResource;

class CatalogProductResource extends BaseResource
{
    public function toArray($request): array
    {
        return array_merge(parent::toArray($request), [
            'price_range' => $this->resource->priceRange(),
        ]);
    }
}
```

## 4. Category Tree

```php
use Aliziodev\ProductCatalog\Models\Category;

// Top-level categories with their children
$tree = Category::whereNull('parent_id')
    ->with('children')
    ->orderBy('position')
    ->get();
```

## 5. SEO Slug Routing

Slugs are permanent-suffixed (e.g. `wireless-mouse-a1b2c3d4`). If you rename a product, the suffix stays the same — old URLs keep resolving automatically.

```php
// Both resolve to the same product
Product::findBySlugOrFail('wireless-mouse-a1b2c3d4');
Product::findBySlugOrFail('ergonomic-mouse-a1b2c3d4'); // after rename
```

## 6. Sitemap Integration

```php
use Aliziodev\ProductCatalog\Models\Product;

$products = Product::published()->select(['slug', 'updated_at'])->cursor();

foreach ($products as $product) {
    // $product->slug → use in your sitemap generator
}
```

---

## Relevant Model API

| Method / Scope | Description |
|---|---|
| `Product::published()` | Filter published products |
| `Product::search($term)` | Full-text search |
| `Product::forBrand($brand)` | Filter by brand |
| `Product::withTag($tag)` | Filter by tag |
| `Product::inStock()` | At least one purchasable variant |
| `Product::bySlug($slug)` | Scope by slug or route key |
| `Product::findBySlug($slug)` | Static finder, returns `?Product` |
| `$product->priceRange()` | `['min' => float, 'max' => float]\|null` |
| `$product->minPrice()` | Lowest active variant price |
| `$product->maxPrice()` | Highest active variant price |
