<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Tests;

use Aliziodev\ProductCatalog\Facades\ProductCatalog;
use Aliziodev\ProductCatalog\ProductCatalogServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ProductCatalogServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'ProductCatalog' => ProductCatalog::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('product-catalog.inventory.driver', 'database');
        $app['config']->set('product-catalog.table_prefix', 'catalog_');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
