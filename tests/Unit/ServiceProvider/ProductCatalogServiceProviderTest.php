<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\ProductCatalogServiceProvider;

// --- registerPublishables() early return when not in console ---

it('registerPublishables returns early when not running in console', function () {
    $ref = new ReflectionProperty($this->app, 'isRunningInConsole');
    $ref->setAccessible(true);
    $original = $ref->getValue($this->app);

    $ref->setValue($this->app, false);

    $provider = new ProductCatalogServiceProvider($this->app);
    $method = new ReflectionMethod($provider, 'registerPublishables');
    $method->setAccessible(true);
    $method->invoke($provider);

    $ref->setValue($this->app, $original);

    expect($this->app->runningInConsole())->toBeTrue();
});

// --- registerCommands() early return when not in console ---

it('registerCommands returns early when not running in console', function () {
    $ref = new ReflectionProperty($this->app, 'isRunningInConsole');
    $ref->setAccessible(true);
    $original = $ref->getValue($this->app);

    $ref->setValue($this->app, false);

    $provider = new ProductCatalogServiceProvider($this->app);
    $method = new ReflectionMethod($provider, 'registerCommands');
    $method->setAccessible(true);
    $method->invoke($provider);

    $ref->setValue($this->app, $original);

    expect($this->app->runningInConsole())->toBeTrue();
});
