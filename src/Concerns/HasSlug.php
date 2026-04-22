<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Generates and maintains a slug + permanent route_key for a model.
 *
 * Slug format: {human-readable-name}-{route_key}
 *
 * - route_key is generated once on create and never changes.
 * - The human-readable prefix auto-updates when the source column (name) changes,
 *   unless the caller explicitly sets the slug field.
 * - Because uniqueness is guaranteed by route_key, no DB uniqueness check is needed.
 */
trait HasSlug
{
    protected static function bootHasSlug(): void
    {
        static::creating(function (self $model) {
            if (empty($model->route_key)) {
                $model->route_key = self::generateRouteKey();
            }

            if (empty($model->slug)) {
                $model->slug = self::buildSlug(
                    $model->{$model->getSlugSourceColumn()},
                    $model->route_key,
                );
            }
        });

        static::updating(function (self $model) {
            if (
                config('product-catalog.slug.auto_generate', true)
                && $model->isDirty($model->getSlugSourceColumn())
                && ! $model->isDirty('slug')
            ) {
                $model->slug = self::buildSlug(
                    $model->{$model->getSlugSourceColumn()},
                    $model->route_key,
                );
            }
        });
    }

    /**
     * Extract the route_key portion from a slug string.
     *
     * For slugs following the {prefix}-{route_key} convention, returns the last
     * dash-separated segment. For plain slugs with no dash, returns the slug itself.
     *
     * Example: 'kaos-premium-abc12345' → 'abc12345'
     */
    public static function extractRouteKey(string $slug): string
    {
        $pos = strrpos($slug, '-');

        return $pos !== false ? substr($slug, $pos + 1) : $slug;
    }

    /**
     * Scope a query to match a slug — tries route_key first, then falls back to
     * exact slug match for manually-set slugs that don't follow the convention.
     */
    public function scopeBySlug(Builder $query, string $slug): Builder
    {
        $routeKey = static::extractRouteKey($slug);

        if ($routeKey === '') {
            return $query->where('slug', $slug);
        }

        return $query->where(function (Builder $q) use ($slug, $routeKey) {
            $q->where('route_key', $routeKey)
                ->orWhere('slug', $slug);
        });
    }

    /**
     * Find a model by slug (route_key-aware). Returns null when not found.
     */
    public static function findBySlug(string $slug): ?static
    {
        return static::query()->bySlug($slug)->first();
    }

    /**
     * Find a model by slug (route_key-aware). Throws ModelNotFoundException when not found.
     */
    public static function findBySlugOrFail(string $slug): static
    {
        return static::query()->bySlug($slug)->firstOrFail();
    }

    protected function getSlugSourceColumn(): string
    {
        return 'name';
    }

    protected static function generateRouteKey(): string
    {
        $length = min(32, max(4, (int) config('product-catalog.slug.route_key_length', 8)));

        return Str::lower(Str::random($length));
    }

    protected static function buildSlug(string $source, string $routeKey): string
    {
        $separator = config('product-catalog.slug.separator', '-');

        return Str::slug($source, $separator).$separator.$routeKey;
    }
}
