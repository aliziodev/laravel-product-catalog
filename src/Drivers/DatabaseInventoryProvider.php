<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Drivers;

use Aliziodev\ProductCatalog\Contracts\InventoryProviderInterface;
use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Aliziodev\ProductCatalog\Enums\MovementType;
use Aliziodev\ProductCatalog\Events\InventoryAdjusted;
use Aliziodev\ProductCatalog\Exceptions\InventoryException;
use Aliziodev\ProductCatalog\Models\InventoryItem;
use Aliziodev\ProductCatalog\Models\InventoryMovement;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;

class DatabaseInventoryProvider implements InventoryProviderInterface
{
    public function getQuantity(ProductVariant $variant): int
    {
        $item = $this->getOrCreateItem($variant);

        return match ($item->policy) {
            InventoryPolicy::Allow => PHP_INT_MAX,
            InventoryPolicy::Deny => 0,
            // Return available quantity (quantity − reserved) so callers know how
            // many units can actually be sold, consistent with Product::scopeInStock().
            InventoryPolicy::Track => $item->availableQuantity(),
        };
    }

    public function isInStock(ProductVariant $variant): bool
    {
        return $this->canFulfill($variant, 1);
    }

    public function canFulfill(ProductVariant $variant, int $quantity): bool
    {
        $item = $this->getOrCreateItem($variant);

        return match ($item->policy) {
            InventoryPolicy::Allow => true,
            InventoryPolicy::Deny => false,
            // Check available quantity (quantity − reserved) so that reserved stock
            // is not double-sold, consistent with Product::scopeInStock().
            InventoryPolicy::Track => $item->availableQuantity() >= $quantity,
        };
    }

    public function adjust(
        ProductVariant $variant,
        int $delta,
        string $reason = '',
        ?Model $reference = null,
    ): void {
        $item = $this->getOrCreateItem($variant);

        if ($item->policy !== InventoryPolicy::Track) {
            return;
        }

        $previous = $item->quantity;
        $newQuantity = $previous + $delta;

        if ($newQuantity < 0) {
            throw InventoryException::insufficientStock(abs($delta), $previous);
        }

        $item->update(['quantity' => $newQuantity]);

        $movement = $this->recordMovement(
            variant: $variant,
            type: $delta >= 0 ? MovementType::Restock : MovementType::Deduction,
            delta: $delta,
            before: $previous,
            after: $newQuantity,
            reason: $reason,
            reference: $reference,
        );

        event(new InventoryAdjusted($variant, $previous, $newQuantity, $reason, $movement));
    }

    public function set(
        ProductVariant $variant,
        int $quantity,
        string $reason = '',
        ?Model $reference = null,
    ): void {
        if ($quantity < 0) {
            throw InventoryException::negativeQuantityNotAllowed();
        }

        $item = $this->getOrCreateItem($variant);
        $previous = $item->quantity;

        $item->update(['quantity' => $quantity]);

        $movement = $this->recordMovement(
            variant: $variant,
            type: MovementType::Set,
            delta: $quantity - $previous,
            before: $previous,
            after: $quantity,
            reason: $reason,
            reference: $reference,
        );

        event(new InventoryAdjusted($variant, $previous, $quantity, $reason, $movement));
    }

    protected function getOrCreateItem(ProductVariant $variant): InventoryItem
    {
        return InventoryItem::firstOrCreate(
            ['variant_id' => $variant->getKey()],
            ['quantity' => 0, 'policy' => InventoryPolicy::Track]
        );
    }

    protected function recordMovement(
        ProductVariant $variant,
        MovementType $type,
        int $delta,
        int $before,
        int $after,
        string $reason,
        ?Model $reference,
    ): InventoryMovement {
        return InventoryMovement::create([
            'variant_id' => $variant->getKey(),
            'type' => $type,
            'delta' => $delta,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reason' => $reason ?: null,
            'referenceable_type' => $reference ? $reference->getMorphClass() : null,
            'referenceable_id' => $reference?->getKey(),
        ]);
    }
}
