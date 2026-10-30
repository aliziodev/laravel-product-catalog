<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function widenPriceColumnsMigration(): Migration
{
    return require __DIR__.'/../../../database/migrations/2026_10_30_000002_widen_catalog_variant_price_columns.php';
}

/**
 * @return array<string, string>
 */
function variantPriceColumnTypes(): array
{
    return collect(Schema::getColumns('catalog_product_variants'))
        ->whereIn('name', ['price', 'compare_price', 'cost_price'])
        ->pluck('type', 'name')
        ->all();
}

it('stores prices above 99,999,999.9999', function () {
    $variant = ProductVariant::factory()->create([
        'price' => '1250000000.5000',
        'compare_price' => '1500000000',
        'cost_price' => '99999999999.2500',
    ]);

    $variant->refresh();

    expect((string) $variant->price)->toBe('1250000000.5000')
        ->and((string) $variant->compare_price)->toBe('1500000000.0000')
        ->and((string) $variant->cost_price)->toBe('99999999999.2500');
});

it('can run again without changing anything', function () {
    $before = variantPriceColumnTypes();

    widenPriceColumnsMigration()->up();

    expect(variantPriceColumnTypes())->toBe($before);
});

it('widens price columns that still have the decimal(12,4) definition', function () {
    Schema::table('catalog_product_variants', function (Blueprint $table) {
        $table->decimal('price', 12, 4)->change();
        $table->decimal('compare_price', 12, 4)->nullable()->change();
        $table->decimal('cost_price', 12, 4)->nullable()->change();
    });

    widenPriceColumnsMigration()->up();

    $columns = collect(Schema::getColumns('catalog_product_variants'))->keyBy('name');

    expect($columns['price']['type'])->toMatch('/\(19,\s*4\)/')
        ->and($columns['price']['nullable'])->toBeFalse()
        ->and($columns['compare_price']['type'])->toMatch('/\(19,\s*4\)/')
        ->and($columns['compare_price']['nullable'])->toBeTrue()
        ->and($columns['cost_price']['type'])->toMatch('/\(19,\s*4\)/')
        ->and($columns['cost_price']['nullable'])->toBeTrue();
})->skip(fn () => DB::getDriverName() === 'sqlite', 'SQLite does not enforce decimal precision.');

it('leaves a price column alone when its definition was already changed', function () {
    Schema::table('catalog_product_variants', function (Blueprint $table) {
        $table->decimal('cost_price', 15, 2)->nullable()->change();
    });

    widenPriceColumnsMigration()->up();

    expect(variantPriceColumnTypes()['cost_price'])->toMatch('/\(15,\s*2\)/');
})->skip(fn () => DB::getDriverName() === 'sqlite', 'SQLite does not enforce decimal precision.');
