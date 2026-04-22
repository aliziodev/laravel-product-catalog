<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog;

use Aliziodev\ProductCatalog\Contracts\InventoryProviderInterface;
use Aliziodev\ProductCatalog\Drivers\DatabaseInventoryProvider;
use Aliziodev\ProductCatalog\Drivers\NullInventoryProvider;
use Aliziodev\ProductCatalog\Exceptions\ProductCatalogException;
use Closure;
use Illuminate\Contracts\Foundation\Application;

class ProductCatalogManager
{
    /** @var array<string, Closure> */
    protected array $customDrivers = [];

    public function __construct(protected Application $app) {}

    /**
     * Resolve an inventory provider by driver name.
     * Falls back to the configured default when $driver is null.
     */
    public function inventory(?string $driver = null): InventoryProviderInterface
    {
        $driver ??= config('product-catalog.inventory.driver', 'database');

        return $this->resolveDriver($driver);
    }

    /**
     * Register a custom inventory driver.
     *
     * ProductCatalog::extend('redis', fn($app) => new RedisInventoryProvider(...));
     */
    public function extend(string $driver, Closure $resolver): void
    {
        $this->customDrivers[$driver] = $resolver;
    }

    protected function resolveDriver(string $driver): InventoryProviderInterface
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
}
