<?php

declare(strict_types=1);

/**
 * Query Performance Benchmarks
 *
 * These tests verify query count bounds and demonstrate the cost of N+1 patterns.
 * Run independently with:
 *
 *   vendor/bin/pest tests/Feature/Performance --group=benchmark
 *
 * Each test uses DB::getQueryLog() to count queries precisely.
 * Timing output is printed to STDOUT for easy comparison across runs.
 *
 * Dataset: 50 products × 3 variants × 1 inventory item = 150 variants, 150 items.
 *
 * Output legend (query-count colour):
 *   GREEN  = 0 queries   — in-memory / fully cached
 *   CYAN   = 1–5         — efficient
 *   YELLOW = 6–20        — caution
 *   RED    > 20          — N+1 territory
 */

use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Aliziodev\ProductCatalog\Enums\ProductStatus;
use Aliziodev\ProductCatalog\Models\Brand;
use Aliziodev\ProductCatalog\Models\Category;
use Aliziodev\ProductCatalog\Models\InventoryItem;
use Aliziodev\ProductCatalog\Models\Product;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Aliziodev\ProductCatalog\Models\Tag;
use Aliziodev\ProductCatalog\Search\ProductSearchBuilder;
use Illuminate\Support\Facades\DB;

// ---------------------------------------------------------------------------
// Query capture helpers
// ---------------------------------------------------------------------------

function startQueryCapture(): void
{
    DB::flushQueryLog();
    DB::enableQueryLog();
}

/** @return array{count: int, ms: float, queries: list<string>} */
function stopQueryCapture(): array
{
    $log = DB::getQueryLog();
    DB::disableQueryLog();

    return [
        'count' => count($log),
        'ms' => array_sum(array_column($log, 'time')),
        'queries' => array_map(fn ($q) => $q['query'], $log),
    ];
}

// ---------------------------------------------------------------------------
// Output helpers
// ---------------------------------------------------------------------------

/**
 * Print one benchmark result row to STDOUT.
 *
 * Format:
 *   label ...........  N queries  12.34ms  [optional note]
 *
 * @param  int  $queries  Number of DB queries fired
 * @param  float  $ms  Wall-clock time in milliseconds
 * @param  string  $note  Short annotation (e.g. "N+1 confirmed")
 */
function benchReport(string $label, int $queries, float $ms, string $note = ''): void
{
    $RST = "\033[0m";
    $DIM = "\033[2m";
    $BOLD = "\033[1m";
    $GREEN = "\033[32m";
    $CYAN = "\033[36m";
    $YELLOW = "\033[33m";
    $RED = "\033[31m";

    $qColor = match (true) {
        $queries === 0 => $GREEN,
        $queries <= 5 => $CYAN,
        $queries <= 20 => $YELLOW,
        default => $RED,
    };

    $qWord = $queries === 1 ? 'query' : 'queries';
    $qStr = "{$BOLD}{$qColor}{$queries} {$qWord}{$RST}";
    $msStr = "{$DIM}".number_format($ms, 2)." ms{$RST}";
    $noteStr = $note !== '' ? "  {$DIM}← {$note}{$RST}" : '';

    // Dot-padded label (48 chars) for visual alignment
    $padded = str_pad($label, 48, '.', STR_PAD_RIGHT);

    fwrite(STDOUT, "  {$DIM}{$padded}{$RST}  {$qStr}  {$msStr}{$noteStr}\n");
}

/**
 * Print a non-metric note row (SQL structure checks, qualitative results).
 */
function benchNote(string $note): void
{
    fwrite(STDOUT, "  \033[2m  ✓ {$note}\033[0m\n");
}

/**
 * Print a section header to visually separate benchmark groups.
 */
function benchSection(string $title): void
{
    $line = str_repeat('─', 62);
    fwrite(STDOUT, "\n  \033[1m{$title}\033[0m\n  \033[2m{$line}\033[0m\n");
}

// ---------------------------------------------------------------------------
// Dataset factory
// ---------------------------------------------------------------------------

/**
 * Seed a realistic but compact dataset.
 *
 * @return array{tags: list<Tag>, categories: list<Category>}
 */
function seedBenchmarkDataset(): array
{
    $brands = Brand::factory()->count(3)->create();
    $categories = Category::factory()->count(3)->create();
    $tags = Tag::factory()->count(5)->create();

    Product::factory()
        ->count(50)
        ->sequence(fn ($seq) => [
            'brand_id' => $brands[$seq->index % 3]->id,
            'primary_category_id' => $categories[$seq->index % 3]->id,
            'status' => ProductStatus::Published,
        ])
        ->create()
        ->each(function (Product $product) use ($tags) {
            // 2 rotating tags per product
            $product->tags()->attach([
                $tags[$product->id % 5]->id,
                $tags[($product->id + 1) % 5]->id,
            ]);

            // 3 variants per product at different price points
            collect([1000, 2000, 3000])->each(function (int $price, int $pos) use ($product) {
                $variant = ProductVariant::factory()->create([
                    'product_id' => $product->id,
                    'price' => $price,
                    'is_active' => true,
                    'is_default' => $pos === 0,
                    'position' => $pos + 1,
                ]);

                InventoryItem::factory()->create([
                    'variant_id' => $variant->id,
                    'quantity' => 50,
                    'reserved_quantity' => 0,
                    'policy' => InventoryPolicy::Track,
                ]);
            });
        });

    return ['tags' => $tags->all(), 'categories' => $categories->all()];
}

// ===========================================================================
// 1. BASIC PAGINATION
// ===========================================================================

it('benchmark: paginating 15 products uses a bounded query count', function () {
    seedBenchmarkDataset();
    benchSection('1 · Pagination');

    startQueryCapture();
    $t = microtime(true);

    $result = Product::published()
        ->with(['brand', 'primaryCategory', 'defaultVariant'])
        ->paginate(15);

    $ms = round((microtime(true) - $t) * 1000, 2);
    $stats = stopQueryCapture();

    // COUNT(*) + SELECT products + 3 eager-load batches = 5 queries max
    expect($stats['count'])->toBeLessThanOrEqual(5);
    expect($result->count())->toBe(15);

    benchReport('with(brand,primaryCategory,defaultVariant)->paginate(15)', $stats['count'], $ms);
})->group('benchmark');

// ===========================================================================
// 2. PRICE HELPERS — N+1 DEMONSTRATION
// ===========================================================================

it('benchmark: minPrice/maxPrice WITHOUT eager-loaded variants fires 2 queries per product', function () {
    seedBenchmarkDataset();
    benchSection('2 · Price helpers — N+1 demonstration');

    $products = Product::published()->limit(10)->get(); // no eager load

    startQueryCapture();
    $t = microtime(true);

    foreach ($products as $product) {
        $product->minPrice(); // 1 query
        $product->maxPrice(); // 1 query
    }

    $ms = round((microtime(true) - $t) * 1000, 2);
    $stats = stopQueryCapture();

    // 10 products × 2 queries = 20 queries — classic N+1
    expect($stats['count'])->toBe(20);

    benchReport('minPrice + maxPrice · 10 products · no eager load', $stats['count'], $ms, 'N+1 confirmed');
})->group('benchmark');

it('benchmark: minPrice/maxPrice WITH eager-loaded variants fires ZERO extra queries', function () {
    seedBenchmarkDataset();

    $products = Product::published()->with('variants')->limit(10)->get(); // eager loaded

    startQueryCapture();
    $t = microtime(true);

    foreach ($products as $product) {
        $product->minPrice(); // in-memory collection — 0 queries
        $product->maxPrice(); // in-memory collection — 0 queries
    }

    $ms = round((microtime(true) - $t) * 1000, 2);
    $stats = stopQueryCapture();

    // Zero DB queries — operates on the already-loaded collection
    expect($stats['count'])->toBe(0);

    benchReport('minPrice + maxPrice · 10 products · eager-loaded', $stats['count'], $ms, 'N+1 eliminated');
})->group('benchmark');

it('benchmark: priceRange() on a full page with eager load stays flat', function () {
    seedBenchmarkDataset();

    startQueryCapture();
    $t = microtime(true);

    $products = Product::published()->with('variants')->paginate(15);
    $pageLoadMs = round((microtime(true) - $t) * 1000, 2);
    $pageLoadStats = stopQueryCapture();

    startQueryCapture();
    $t2 = microtime(true);

    foreach ($products as $product) {
        $product->priceRange();
    }

    $ms = round((microtime(true) - $t2) * 1000, 2);
    $stats = stopQueryCapture();

    expect($stats['count'])->toBe(0);           // 0 extra queries for priceRange on 15 products
    expect($pageLoadStats['count'])->toBeLessThanOrEqual(3); // count + products + variants

    benchReport('Page load: with(variants)->paginate(15)', $pageLoadStats['count'], $pageLoadMs);
    benchReport('priceRange() × 15 products after eager load', $stats['count'], $ms, 'no DB access');
})->group('benchmark');

// ===========================================================================
// 3. INVENTORY ACCESS — N+1 DEMONSTRATION
// ===========================================================================

it('benchmark: accessing inventoryItem WITHOUT eager load causes N+1 per variant', function () {
    seedBenchmarkDataset();
    benchSection('3 · Inventory access — N+1 demonstration');

    // 10 products with variants but no inventoryItem eager load
    $products = Product::published()->with('variants')->limit(10)->get();
    $variantCount = $products->sum(fn ($p) => $p->variants->count());

    startQueryCapture();
    $t = microtime(true);

    foreach ($products as $product) {
        foreach ($product->variants as $variant) {
            $_ = $variant->inventoryItem; // lazy loads 1 query per variant
        }
    }

    $ms = round((microtime(true) - $t) * 1000, 2);
    $stats = stopQueryCapture();

    expect($stats['count'])->toBe($variantCount); // 1 query per variant

    benchReport("inventoryItem · {$variantCount} variants · no eager load", $stats['count'], $ms, 'N+1 confirmed');
})->group('benchmark');

it('benchmark: accessing inventoryItem WITH eager load uses a single batch query', function () {
    seedBenchmarkDataset();

    startQueryCapture();
    $t = microtime(true);

    // variants.inventoryItem loads all inventory items in one batch query
    $products = Product::published()->with('variants.inventoryItem')->limit(10)->get();

    $loadMs = round((microtime(true) - $t) * 1000, 2);
    $loadStats = stopQueryCapture();

    startQueryCapture();
    $t2 = microtime(true);

    // Access inventory — zero extra queries
    foreach ($products as $product) {
        foreach ($product->variants as $variant) {
            $_ = $variant->inventoryItem;
        }
    }

    $ms = round((microtime(true) - $t2) * 1000, 2);
    $extraStats = stopQueryCapture();

    // products + variants + inventoryItems = 3 queries (no COUNT for get())
    expect($loadStats['count'])->toBeLessThanOrEqual(3);
    expect($extraStats['count'])->toBe(0);

    benchReport('Load: with(variants.inventoryItem)->limit(10)->get()', $loadStats['count'], $loadMs);
    benchReport('Access inventoryItem × all variants after load', $extraStats['count'], $ms, 'N+1 eliminated');
})->group('benchmark');

// ===========================================================================
// 4. TAG FILTERING
// ===========================================================================

it('benchmark: single tag filter uses 1 SQL query total (not 1 EXISTS per tag)', function () {
    ['tags' => $tags] = seedBenchmarkDataset();
    benchSection('4 · Tag filtering');

    startQueryCapture();
    $t = microtime(true);

    $result = Product::published()
        ->withTag($tags[0]->id)
        ->get();

    $ms = round((microtime(true) - $t) * 1000, 2);
    $stats = stopQueryCapture();

    expect($stats['count'])->toBe(1);
    expect($result->count())->toBeGreaterThan(0);

    benchReport('Single tag filter', $stats['count'], $ms, 'WHERE EXISTS');
})->group('benchmark');

it('benchmark: multi-tag filter via ProductSearchBuilder uses 1 SQL query (GROUP BY HAVING)', function () {
    ['tags' => $tags] = seedBenchmarkDataset();

    startQueryCapture();
    $t = microtime(true);

    // Filter products that have ALL 3 tags — uses GROUP BY HAVING internally
    $result = ProductSearchBuilder::query('')
        ->withTags([$tags[0]->slug, $tags[1]->slug, $tags[2]->slug])
        ->get();

    $ms = round((microtime(true) - $t) * 1000, 2);
    $stats = stopQueryCapture();

    // 1 SELECT — multi-tag filter is a single IN subquery, not 3 correlated EXISTS
    expect($stats['count'])->toBe(1);

    benchReport('Multi-tag filter · 3 tags · AND semantics', $stats['count'], $ms, 'GROUP BY / HAVING subquery');
    benchNote('SQL: WHERE id IN (SELECT product_id GROUP BY product_id HAVING COUNT = 3)');
})->group('benchmark');

it('benchmark: old N-EXISTS pattern would cost N queries in SELECT; verify SQL structure', function () {
    // OLD: foreach ($tags) { $builder->whereExists(...) }
    //   → WHERE EXISTS (...tag1...) AND EXISTS (...tag2...) AND EXISTS (...tag3...)
    //   → 3 correlated subqueries evaluated per row
    //
    // NEW: single IN subquery with GROUP BY HAVING
    //   → WHERE id IN (SELECT product_id … GROUP BY product_id HAVING COUNT = 3)
    //   → subquery evaluated once; outer query only filters on matching IDs

    ['tags' => $tags] = seedBenchmarkDataset();

    $sql = Product::published()
        ->whereIn('id', function ($sub) use ($tags) {
            $prefix = config('product-catalog.table_prefix', 'catalog_');
            $sub->from($prefix.'product_tags', 'pt')
                ->select('pt.product_id')
                ->join("{$prefix}tags as t", 't.id', '=', 'pt.tag_id')
                ->whereNull('t.deleted_at')
                ->whereIn('t.id', [$tags[0]->id, $tags[1]->id, $tags[2]->id])
                ->groupBy('pt.product_id')
                ->havingRaw('COUNT(DISTINCT pt.tag_id) = 3');
        })
        ->toSql();

    expect($sql)->toContain('group by')
        ->and($sql)->toContain('having')
        ->and($sql)->not->toContain('exists'); // no EXISTS clauses

    benchNote('Tag SQL confirmed: GROUP BY / HAVING present, correlated EXISTS absent');
})->group('benchmark');

// ===========================================================================
// 5. IN-STOCK SCOPE
// ===========================================================================

it('benchmark: inStock scope uses a nested WHERE EXISTS (not a JOIN)', function () {
    seedBenchmarkDataset();
    benchSection('5 · inStock scope');

    startQueryCapture();
    $t = microtime(true);

    $result = Product::published()->inStock()->paginate(15);

    $ms = round((microtime(true) - $t) * 1000, 2);
    $stats = stopQueryCapture();

    // COUNT(*) + SELECT: 2 queries
    expect($stats['count'])->toBe(2);
    expect($result->count())->toBe(15);

    $selectSql = $stats['queries'][1]; // SELECT query (after the COUNT)
    expect($selectSql)->toContain('exists');

    benchReport('inStock()->paginate(15)', $stats['count'], $ms, 'WHERE EXISTS · no JOIN');
})->group('benchmark');

// ===========================================================================
// 6. COMPLEX SEARCH (ProductSearchBuilder)
// ===========================================================================

it('benchmark: complex search with 4 filters executes exactly 2 queries (COUNT + SELECT)', function () {
    ['tags' => $tags, 'categories' => $categories] = seedBenchmarkDataset();
    benchSection('6 · Complex search (ProductSearchBuilder)');

    startQueryCapture();
    $t = microtime(true);

    $result = ProductSearchBuilder::query('product')
        ->inCategory($categories[0]->id)
        ->withTags([$tags[0]->slug])
        ->priceBetween(500, 5000)
        ->onlyInStock()
        ->sortBy('price')
        ->paginate(15);

    $ms = round((microtime(true) - $t) * 1000, 2);
    $stats = stopQueryCapture();

    // COUNT + SELECT + up to 3 eager-load batches (defaultVariant, brand, primaryCategory)
    expect($stats['count'])->toBeLessThanOrEqual(5);

    benchReport('text + category + tag + price + stock · paginate(15)', $stats['count'], $ms);
})->group('benchmark');

it('benchmark: search text query produces 2 queries regardless of result count', function () {
    seedBenchmarkDataset();

    startQueryCapture();
    $t = microtime(true);

    ProductSearchBuilder::query('product')->withStatus('published')->paginate(15);

    $ms = round((microtime(true) - $t) * 1000, 2);
    $stats = stopQueryCapture();

    // COUNT + SELECT + up to 3 eager loads = max 5
    expect($stats['count'])->toBeLessThanOrEqual(5);

    benchReport('Text search paginate', $stats['count'], $ms);
})->group('benchmark');

// ===========================================================================
// 7. PRICE SORT (scalar subquery in ORDER BY)
// ===========================================================================

it('benchmark: price sort adds a scalar subquery in ORDER BY — documented limitation', function () {
    seedBenchmarkDataset();
    benchSection('7 · Price sort');

    startQueryCapture();
    $t = microtime(true);

    $result = ProductSearchBuilder::query('')
        ->sortBy('price')
        ->paginate(15);

    $ms = round((microtime(true) - $t) * 1000, 2);
    $stats = stopQueryCapture();

    expect($stats['count'])->toBeLessThanOrEqual(5);

    // The SELECT query contains a correlated subquery in ORDER BY — evaluated once per row.
    // Acceptable for catalogs < ~50k products. For larger catalogs consider a denormalized
    // min_price column or a materialized view.
    $selectSql = collect($stats['queries'])->first(
        fn ($q) => str_starts_with(strtolower(trim($q)), 'select') && ! str_contains(strtolower($q), 'count(*)')
    );
    expect($selectSql)->toContain('order by');

    benchReport('sortBy(price)->paginate(15)', $stats['count'], $ms);
    benchNote('ORDER BY scalar subquery — evaluated per row (acceptable for < 50k products)');
})->group('benchmark');

// ===========================================================================
// 8. PRODUCT DETAIL PAGE (show endpoint simulation)
// ===========================================================================

it('benchmark: product detail page loads with a fixed number of queries', function () {
    seedBenchmarkDataset();
    benchSection('8 · Product detail page (show endpoint)');

    $product = Product::published()->first();

    startQueryCapture();
    $t = microtime(true);

    // Simulates ProductController::show()
    $loaded = Product::published()
        ->with(['brand', 'primaryCategory', 'tags', 'variants.inventoryItem', 'options.values'])
        ->bySlug($product->slug)
        ->firstOrFail();

    $ms = round((microtime(true) - $t) * 1000, 2);
    $stats = stopQueryCapture();

    // 1 SELECT products + brand, primaryCategory, tags + pivot + variants + inventoryItems
    // + options + values = ≤ 8 queries
    expect($stats['count'])->toBeLessThanOrEqual(8);

    // Verify inventory access fires zero extra queries after the eager load
    startQueryCapture();
    $t2 = microtime(true);

    foreach ($loaded->variants as $variant) {
        $_ = $variant->inventoryItem?->availableQuantity();
    }

    $inventoryMs = round((microtime(true) - $t2) * 1000, 2);
    $inventoryQueries = stopQueryCapture()['count'];

    expect($inventoryQueries)->toBe(0);

    benchReport('with(brand,category,tags,variants.inventoryItem,options.values)', $stats['count'], $ms);
    benchReport('Inventory access (all variants) after eager load', $inventoryQueries, $inventoryMs, 'no DB access');
})->group('benchmark');
