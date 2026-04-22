<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Contracts\SearchDriverInterface;
use Aliziodev\ProductCatalog\Exceptions\ProductCatalogException;
use Aliziodev\ProductCatalog\ProductCatalogManager;
use Aliziodev\ProductCatalog\Search\DatabaseSearchDriver;
use Aliziodev\ProductCatalog\Search\ScoutSearchDriver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Laravel\Scout\Builder;

// ── extendSearch() ────────────────────────────────────────────────────────────

it('extendSearch() registers a custom search driver resolver', function () {
    $manager = app(ProductCatalogManager::class);

    $manager->extendSearch('custom', fn ($app) => new class implements SearchDriverInterface
    {
        public function paginate(string $query, array $filters, int $perPage, int $page): LengthAwarePaginator
        {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        public function get(string $query, array $filters): Collection
        {
            return new Collection;
        }
    });

    $driver = $manager->search('custom');

    expect($driver)->toBeInstanceOf(SearchDriverInterface::class);
});

it('extendSearch() closure receives the application container (line 95)', function () {
    $manager = app(ProductCatalogManager::class);
    $received = null;

    $manager->extendSearch('spy', function ($app) use (&$received) {
        $received = $app;

        return new class implements SearchDriverInterface
        {
            public function paginate(string $query, array $filters, int $perPage, int $page): LengthAwarePaginator
            {
                return new LengthAwarePaginator([], 0, $perPage, $page);
            }

            public function get(string $query, array $filters): Collection
            {
                return new Collection;
            }
        };
    });

    $manager->search('spy');

    expect($received)->toBeInstanceOf(Application::class);
});

// ── built-in drivers ──────────────────────────────────────────────────────────

it('search("database") returns a DatabaseSearchDriver (line 99)', function () {
    $driver = app(ProductCatalogManager::class)->search('database');

    expect($driver)->toBeInstanceOf(DatabaseSearchDriver::class);
});

it('search("scout") throws RuntimeException when laravel/scout is not installed (line 100)', function () {
    expect(fn () => app(ProductCatalogManager::class)->search('scout'))
        ->toThrow(RuntimeException::class, 'ScoutSearchDriver requires laravel/scout');
})->skip(
    fn () => class_exists(Builder::class),
    'laravel/scout is installed — RuntimeException guard is not triggered'
);

it('search("scout") returns a ScoutSearchDriver when laravel/scout is installed', function () {
    $driver = app(ProductCatalogManager::class)->search('scout');

    expect($driver)->toBeInstanceOf(ScoutSearchDriver::class);
})->skip(
    fn () => ! class_exists(Builder::class),
    'laravel/scout is not installed'
);

// ── default config fallback ───────────────────────────────────────────────────

it('search() uses the configured default driver when no argument is given', function () {
    config(['product-catalog.search.driver' => 'database']);

    $driver = app(ProductCatalogManager::class)->search();

    expect($driver)->toBeInstanceOf(DatabaseSearchDriver::class);
});

// ── unknown driver ────────────────────────────────────────────────────────────

it('search() throws ProductCatalogException for an unregistered driver name', function () {
    expect(fn () => app(ProductCatalogManager::class)->search('nonexistent'))
        ->toThrow(ProductCatalogException::class);
});
