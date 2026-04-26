<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Enums;

enum ProductStatus: string
{
    /** Work in progress — not visible to anyone. */
    case Draft = 'draft';

    /** Publicly listed and purchasable. */
    case Published = 'published';

    /**
     * Live but not publicly listed.
     * Accessible via direct URL (members-only, wholesale, pre-order invite).
     */
    case Private = 'private';

    /** Discontinued — no longer available. */
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Private => 'Private',
            self::Archived => 'Archived',
        };
    }

    /** True for statuses that should appear in public catalog listings. */
    public function isPublic(): bool
    {
        return $this === self::Published;
    }

    /** True for statuses where the product is live (publicly or privately). */
    public function isLive(): bool
    {
        return $this === self::Published || $this === self::Private;
    }
}
