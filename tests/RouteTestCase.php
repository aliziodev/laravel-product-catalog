<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Tests;

abstract class RouteTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('product-catalog.routes.enabled', true);
        $app['config']->set('product-catalog.routes.prefix', 'catalog');
        $app['config']->set('product-catalog.routes.middleware', []);
    }
}
