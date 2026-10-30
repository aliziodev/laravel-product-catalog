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
        $app['config']->set('database.connections.testing', $this->databaseConnection());
        $app['config']->set('product-catalog.inventory.driver', 'database');
        $app['config']->set('product-catalog.table_prefix', 'catalog_');
    }

    /**
     * In-memory SQLite by default. Set DB_CONNECTION to pgsql or mysql, plus
     * DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD, to run the
     * suite against a database server (the CI database jobs do this).
     *
     * @return array<string, mixed>
     */
    protected function databaseConnection(): array
    {
        $server = [
            'host' => env('DB_HOST', '127.0.0.1'),
            'database' => env('DB_DATABASE', 'catalog'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'prefix' => '',
        ];

        return match (env('DB_CONNECTION', 'sqlite')) {
            'pgsql' => $server + [
                'driver' => 'pgsql',
                'port' => env('DB_PORT', 5432),
                'charset' => 'utf8',
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ],
            'mysql', 'mariadb' => $server + [
                'driver' => env('DB_CONNECTION'),
                'port' => env('DB_PORT', 3306),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'strict' => true,
            ],
            default => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        };
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
