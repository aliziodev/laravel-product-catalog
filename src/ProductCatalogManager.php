<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog;

use Aliziodev\ProductCatalog\Contracts\InventoryProviderInterface;
use Aliziodev\ProductCatalog\Contracts\SearchDriverInterface;
use Aliziodev\ProductCatalog\Drivers\DatabaseInventoryProvider;
use Aliziodev\ProductCatalog\Drivers\NullInventoryProvider;
use Aliziodev\ProductCatalog\Exceptions\ProductCatalogException;
use Aliziodev\ProductCatalog\Search\DatabaseSearchDriver;
use Aliziodev\ProductCatalog\Search\ScoutSearchDriver;
use Closure;
use Illuminate\Contracts\Foundation\Application;

class ProductCatalogManager
{
    /** @var array<string, Closure> */
    protected array $customDrivers = [];

    /** @var array<string, Closure> */
    protected array $customSearchDrivers = [];

    public function __construct(protected Application $app) {}

    // -------------------------------------------------------------------------
    // Inventory
    // -------------------------------------------------------------------------

    /**
     * Resolve an inventory provider by driver name.
     * Falls back to the configured default when $driver is null.
     */
    public function inventory(?string $driver = null): InventoryProviderInterface
    {
        $driver ??= config('product-catalog.inventory.driver', 'database');

        return $this->resolveInventoryDriver($driver);
    }

    /**
     * Register a custom inventory driver.
     *
     * ProductCatalog::extend('erp', fn($app) => new ErpInventoryProvider(...));
     */
    public function extend(string $driver, Closure $resolver): void
    {
        $this->customDrivers[$driver] = $resolver;
    }

    protected function resolveInventoryDriver(string $driver): InventoryProviderInterface
    {
        if (isset($this->customDrivers[$driver])) {
            return ($this->customDrivers[$driver])($this->app);
        }

        return match ($driver) {
            'database' => $this->app->make(DatabaseInventoryProvider::class),
            'null' => $this->app->make(NullInventoryProvider::class),
            default => throw ProductCatalogException::driverNotFound($driver),
        };
    }

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------

    /**
     * Resolve a search driver by name.
     * Falls back to the configured default when $driver is null.
     *
     * Built-in drivers: 'database', 'scout'.
     */
    public function search(?string $driver = null): SearchDriverInterface
    {
        $driver ??= config('product-catalog.search.driver', 'database');

        return $this->resolveSearchDriver($driver);
    }

    /**
     * Register a custom search driver.
     *
     * ProductCatalog::extendSearch('typesense', fn($app) => new TypesenseSearchDriver(...));
     */
    public function extendSearch(string $driver, Closure $resolver): void
    {
        $this->customSearchDrivers[$driver] = $resolver;
    }

    protected function resolveSearchDriver(string $driver): SearchDriverInterface
    {
        if (isset($this->customSearchDrivers[$driver])) {
            return ($this->customSearchDrivers[$driver])($this->app);
        }

        return match ($driver) {
            'database' => $this->app->make(DatabaseSearchDriver::class),
            'scout' => $this->app->make(ScoutSearchDriver::class),
            default => throw ProductCatalogException::driverNotFound($driver),
        };
    }
}
