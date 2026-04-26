<?php

declare(strict_types=1);

use Aliziodev\ProductCatalog\Enums\ProductStatus;

it('has the correct string values', function () {
    expect(ProductStatus::Draft->value)->toBe('draft')
        ->and(ProductStatus::Published->value)->toBe('published')
        ->and(ProductStatus::Private->value)->toBe('private')
        ->and(ProductStatus::Archived->value)->toBe('archived');
});

it('isPublic returns true only for Published', function () {
    expect(ProductStatus::Published->isPublic())->toBeTrue()
        ->and(ProductStatus::Draft->isPublic())->toBeFalse()
        ->and(ProductStatus::Private->isPublic())->toBeFalse()
        ->and(ProductStatus::Archived->isPublic())->toBeFalse();
});

it('isLive returns true for Published and Private', function () {
    expect(ProductStatus::Published->isLive())->toBeTrue()
        ->and(ProductStatus::Private->isLive())->toBeTrue()
        ->and(ProductStatus::Draft->isLive())->toBeFalse()
        ->and(ProductStatus::Archived->isLive())->toBeFalse();
});

it('returns human-readable labels', function () {
    expect(ProductStatus::Draft->label())->toBe('Draft')
        ->and(ProductStatus::Published->label())->toBe('Published')
        ->and(ProductStatus::Private->label())->toBe('Private')
        ->and(ProductStatus::Archived->label())->toBe('Archived');
});
