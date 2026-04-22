<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Tests\RouteTestCase;
use Aliziodev\ProductCatalog\Tests\TestCase;

uses(TestCase::class)->in('Feature/Commands', 'Feature/Inventory', 'Feature/Models', 'Unit');
uses(RouteTestCase::class)->in('Feature/Http');
