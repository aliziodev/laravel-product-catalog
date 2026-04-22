<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    public function isPublic(): bool
    {
        return $this === self::Published;
    }
}
