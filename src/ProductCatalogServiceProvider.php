<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog;

use Aliziodev\ProductCatalog\Console\Commands\InstallCommand;
use Aliziodev\ProductCatalog\Console\Commands\SeedDemoCommand;
use Aliziodev\ProductCatalog\Contracts\InventoryProviderInterface;
use Aliziodev\ProductCatalog\Contracts\SearchDriverInterface;
use Aliziodev\ProductCatalog\Models\Product;
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

        $this->app->bind(
            SearchDriverInterface::class,
            fn ($app) => $app->make('product-catalog')->search()
        );
    }

    public function boot(): void
    {
        $this->validateConfiguration();
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

    /**
     * Validate critical configuration values and throw early with a helpful message
     * rather than letting an obscure error surface deep in a request.
     *
     * Only validates values the developer must set explicitly (e.g. a custom model
     * class). Driver names are not validated here because custom drivers registered
     * via extend() / extendSearch() arrive after boot.
     */
    protected function validateConfiguration(): void
    {
        $modelClass = config('product-catalog.model');

        if (! is_string($modelClass) || $modelClass === '') {
            throw new \InvalidArgumentException(
                'product-catalog.model must be a non-empty class name string.'
            );
        }

        if (! class_exists($modelClass)) {
            throw new \InvalidArgumentException(
                "product-catalog.model [{$modelClass}] does not exist. "
                .'Ensure the class is autoloaded and the config value is correct.'
            );
        }

        if ($modelClass !== Product::class && ! is_subclass_of($modelClass, Product::class)) {
            throw new \InvalidArgumentException(
                "product-catalog.model [{$modelClass}] must extend "
                .Product::class.'.'
            );
        }
    }
}
