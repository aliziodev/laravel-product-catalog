<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Search\DatabaseSearchDriver;
use Aliziodev\ProductCatalog\Search\ScoutSearchDriver;
use Laravel\Scout\Builder;

// ── constructor guard ─────────────────────────────────────────────────────────

it('throws RuntimeException when laravel/scout is not installed', function () {
    // laravel/scout is only suggested, not required — class_exists() returns false
    // in the default test environment, so the constructor guard fires.
    expect(fn () => new ScoutSearchDriver(app(DatabaseSearchDriver::class)))
        ->toThrow(RuntimeException::class, 'ScoutSearchDriver requires laravel/scout');
})->skip(
    fn () => class_exists(Builder::class),
    'laravel/scout is installed — RuntimeException guard is not triggered'
);

// ── when Scout is available ───────────────────────────────────────────────────

it('can be instantiated when laravel/scout is installed', function () {
    $driver = new ScoutSearchDriver(app(DatabaseSearchDriver::class));

    expect($driver)->toBeInstanceOf(ScoutSearchDriver::class);
})->skip(
    fn () => ! class_exists(Builder::class),
    'laravel/scout is not installed'
);
