<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog;

use Aliziodev\ProductCatalog\Console\Commands\InstallCommand;
use Aliziodev\ProductCatalog\Console\Commands\SeedDemoCommand;
use Aliziodev\ProductCatalog\Contracts\InventoryProviderInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ProductCatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/product-catalog.php',
            'product-catalog'
        );

        $this->app->singleton('product-catalog', fn ($app) => new ProductCatalogManager($app));

        $this->app->bind(
            InventoryProviderInterface::class,
            fn ($app) => $app->make('product-catalog')->inventory()
        );
    }

    public function boot(): void
    {
        $this->registerPublishables();
        $this->registerMigrations();
        $this->registerRoutes();
        $this->registerCommands();
    }

    protected function registerPublishables(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/product-catalog.php' => config_path('product-catalog.php'),
        ], 'product-catalog-config');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'product-catalog-migrations');

        $this->publishes([
            __DIR__.'/../database/factories/' => database_path('factories'),
        ], 'product-catalog-factories');
    }

    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function registerRoutes(): void
    {
        if (! config('product-catalog.routes.enabled', false)) {
            return;
        }

        Route::prefix(config('product-catalog.routes.prefix', 'catalog'))
            ->middleware(config('product-catalog.routes.middleware', ['api']))
            ->group(__DIR__.'/../routes/api.php');
    }

    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallCommand::class,
            SeedDemoCommand::class,
        ]);
    }
}
