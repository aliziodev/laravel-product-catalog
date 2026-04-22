<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Models\Product;
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

// --- validateConfiguration() ---

it('validateConfiguration() passes silently with the default model', function () {
    $provider = new ProductCatalogServiceProvider($this->app);
    $method = new ReflectionMethod($provider, 'validateConfiguration');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($provider))->not->toThrow(InvalidArgumentException::class);
});

it('validateConfiguration() throws when model config is an empty string', function () {
    config(['product-catalog.model' => '']);

    $provider = new ProductCatalogServiceProvider($this->app);
    $method = new ReflectionMethod($provider, 'validateConfiguration');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($provider))
        ->toThrow(InvalidArgumentException::class, 'must be a non-empty class name string');
});

it('validateConfiguration() throws when model class does not exist', function () {
    config(['product-catalog.model' => 'App\\Models\\NonExistentProduct']);

    $provider = new ProductCatalogServiceProvider($this->app);
    $method = new ReflectionMethod($provider, 'validateConfiguration');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($provider))
        ->toThrow(InvalidArgumentException::class, 'does not exist');
});

it('validateConfiguration() throws when model does not extend the base Product', function () {
    config(['product-catalog.model' => stdClass::class]);

    $provider = new ProductCatalogServiceProvider($this->app);
    $method = new ReflectionMethod($provider, 'validateConfiguration');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($provider))
        ->toThrow(InvalidArgumentException::class, 'must extend');
});

it('validateConfiguration() passes when model is a valid subclass of Product', function () {
    // Create an anonymous subclass — class_exists() will return true for it
    $subclass = new class extends Product {};

    config(['product-catalog.model' => $subclass::class]);

    $provider = new ProductCatalogServiceProvider($this->app);
    $method = new ReflectionMethod($provider, 'validateConfiguration');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($provider))->not->toThrow(InvalidArgumentException::class);
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
