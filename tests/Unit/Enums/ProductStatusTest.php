<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Enums\ProductStatus;

it('has the correct string values', function () {
    expect(ProductStatus::Draft->value)->toBe('draft')
        ->and(ProductStatus::Published->value)->toBe('published')
        ->and(ProductStatus::Archived->value)->toBe('archived');
});

it('correctly identifies public status', function () {
    expect(ProductStatus::Published->isPublic())->toBeTrue()
        ->and(ProductStatus::Draft->isPublic())->toBeFalse()
        ->and(ProductStatus::Archived->isPublic())->toBeFalse();
});

it('returns human-readable labels', function () {
    expect(ProductStatus::Draft->label())->toBe('Draft')
        ->and(ProductStatus::Published->label())->toBe('Published')
        ->and(ProductStatus::Archived->label())->toBe('Archived');
});
