<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Events;

use Aliziodev\ProductCatalog\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductPublished
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Product $product) {}
}
