<?php

declare(strict_types=1);

namespace Aliziodev\ProductCatalog\Drivers;

use Aliziodev\ProductCatalog\Contracts\InventoryProviderInterface;
use Aliziodev\ProductCatalog\Enums\InventoryPolicy;
use Aliziodev\ProductCatalog\Enums\MovementType;
use Aliziodev\ProductCatalog\Events\InventoryAdjusted;
use Aliziodev\ProductCatalog\Events\InventoryLowStock;
use Aliziodev\ProductCatalog\Events\InventoryOutOfStock;
use Aliziodev\ProductCatalog\Events\InventoryReserved;
use Aliziodev\ProductCatalog\Exceptions\InventoryException;
use Aliziodev\ProductCatalog\Models\InventoryItem;
use Aliziodev\ProductCatalog\Models\InventoryMovement;
use Aliziodev\ProductCatalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
        $this->withItemLock($variant, function (InventoryItem $item) use ($variant, $delta, $reason, $reference) {
            if ($item->policy !== InventoryPolicy::Track) {
                return;
            }

            $previous = $item->quantity;
            $newQuantity = $previous + $delta;

            if ($newQuantity < 0) {
                throw InventoryException::insufficientStock(abs($delta), $previous);
            }

            $availableBefore = max(0, $previous - $item->reserved_quantity);
            $item->update(['quantity' => $newQuantity]);
            $availableAfter = max(0, $newQuantity - $item->reserved_quantity);

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

            if ($delta < 0) {
                $this->fireStockAlerts($variant, $availableBefore, $availableAfter, $item->low_stock_threshold, $movement);
            }
        });
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

        $this->withItemLock($variant, function (InventoryItem $item) use ($variant, $quantity, $reason, $reference) {
            $previous = $item->quantity;
            $availableBefore = max(0, $previous - $item->reserved_quantity);
            $item->update(['quantity' => $quantity]);
            $availableAfter = max(0, $quantity - $item->reserved_quantity);

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

            if ($quantity < $previous) {
                $this->fireStockAlerts($variant, $availableBefore, $availableAfter, $item->low_stock_threshold, $movement);
            }
        });
    }

    public function reserve(
        ProductVariant $variant,
        int $quantity,
        string $reason = '',
        ?Model $reference = null,
    ): void {
        $this->withItemLock($variant, function (InventoryItem $item) use ($variant, $quantity, $reason, $reference) {
            if ($item->policy !== InventoryPolicy::Track) {
                return;
            }

            if ($item->availableQuantity() < $quantity) {
                throw InventoryException::insufficientStock($quantity, $item->availableQuantity());
            }

            $reservedBefore = $item->reserved_quantity;
            $availableBefore = max(0, $item->quantity - $reservedBefore);
            $item->reserve($quantity);
            $reservedAfter = $reservedBefore + $quantity;
            $availableAfter = max(0, $item->quantity - $reservedAfter);

            $movement = $this->recordMovement(
                variant: $variant,
                type: MovementType::Reserve,
                delta: $quantity,
                before: $item->quantity,
                after: $item->quantity,
                reason: $reason,
                reference: $reference,
                reservedBefore: $reservedBefore,
                reservedAfter: $reservedAfter,
            );

            event(new InventoryReserved($variant, MovementType::Reserve, $quantity, $reservedBefore, $reservedAfter, $reason, $movement));
            $this->fireStockAlerts($variant, $availableBefore, $availableAfter, $item->low_stock_threshold, $movement);
        });
    }

    public function release(
        ProductVariant $variant,
        int $quantity,
        string $reason = '',
        ?Model $reference = null,
    ): void {
        $this->withItemLock($variant, function (InventoryItem $item) use ($variant, $quantity, $reason, $reference) {
            if ($item->policy !== InventoryPolicy::Track) {
                return;
            }

            $reservedBefore = $item->reserved_quantity;
            $actualRelease = min($quantity, $reservedBefore);
            $item->release($actualRelease);
            $reservedAfter = $reservedBefore - $actualRelease;

            $movement = $this->recordMovement(
                variant: $variant,
                type: MovementType::Release,
                delta: -$actualRelease,
                before: $item->quantity,
                after: $item->quantity,
                reason: $reason,
                reference: $reference,
                reservedBefore: $reservedBefore,
                reservedAfter: $reservedAfter,
            );

            event(new InventoryReserved($variant, MovementType::Release, -$actualRelease, $reservedBefore, $reservedAfter, $reason, $movement));
        });
    }

    public function commit(
        ProductVariant $variant,
        int $quantity,
        string $reason = '',
        ?Model $reference = null,
    ): void {
        $this->withItemLock($variant, function (InventoryItem $item) use ($variant, $quantity, $reason, $reference) {
            if ($item->policy !== InventoryPolicy::Track) {
                return;
            }

            if ($item->reserved_quantity < $quantity) {
                throw InventoryException::insufficientReservation($quantity, $item->reserved_quantity);
            }

            $qtyBefore = $item->quantity;
            $reservedBefore = $item->reserved_quantity;
            $qtyAfter = $qtyBefore - $quantity;
            $reservedAfter = $reservedBefore - $quantity;

            $item->update([
                'quantity' => $qtyAfter,
                'reserved_quantity' => $reservedAfter,
            ]);

            $movement = $this->recordMovement(
                variant: $variant,
                type: MovementType::Deduction,
                delta: -$quantity,
                before: $qtyBefore,
                after: $qtyAfter,
                reason: $reason,
                reference: $reference,
                reservedBefore: $reservedBefore,
                reservedAfter: $reservedAfter,
            );

            event(new InventoryAdjusted($variant, $qtyBefore, $qtyAfter, $reason, $movement));
        });
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Ensure the inventory row exists, then run $callback inside a transaction
     * with a pessimistic write lock on the row.
     *
     * Separating firstOrCreate (outside) from lockForUpdate (inside) avoids a
     * deadlock: firstOrCreate on a non-existent row within a transaction can
     * escalate to a gap lock that conflicts with concurrent inserts.
     */
    protected function withItemLock(ProductVariant $variant, \Closure $callback): mixed
    {
        $this->getOrCreateItem($variant);

        return DB::transaction(function () use ($variant, $callback) {
            $item = InventoryItem::where('variant_id', $variant->getKey())
                ->lockForUpdate()
                ->first();

            return $callback($item);
        });
    }

    protected function getOrCreateItem(ProductVariant $variant): InventoryItem
    {
        return InventoryItem::firstOrCreate(
            ['variant_id' => $variant->getKey()],
            ['quantity' => 0, 'policy' => InventoryPolicy::Track]
        );
    }

    /**
     * Fire InventoryOutOfStock or InventoryLowStock when available quantity
     * crosses a threshold boundary. Out-of-stock takes precedence — both events
     * are never fired for the same operation.
     */
    private function fireStockAlerts(
        ProductVariant $variant,
        int $availableBefore,
        int $availableAfter,
        ?int $threshold,
        ?InventoryMovement $movement,
    ): void {
        if ($availableBefore > 0 && $availableAfter <= 0) {
            event(new InventoryOutOfStock($variant, $movement));

            return;
        }

        if ($threshold !== null && $availableBefore > $threshold && $availableAfter <= $threshold) {
            event(new InventoryLowStock($variant, $availableAfter, $threshold, $movement));
        }
    }

    protected function recordMovement(
        ProductVariant $variant,
        MovementType $type,
        int $delta,
        int $before,
        int $after,
        string $reason,
        ?Model $reference,
        ?int $reservedBefore = null,
        ?int $reservedAfter = null,
    ): InventoryMovement {
        return InventoryMovement::create([
            'variant_id' => $variant->getKey(),
            'type' => $type,
            'delta' => $delta,
            'quantity_before' => $before,
            'quantity_after' => $after,
            'reserved_before' => $reservedBefore,
            'reserved_after' => $reservedAfter,
            'reason' => $reason ?: null,
            'referenceable_type' => $reference ? $reference->getMorphClass() : null,
            'referenceable_id' => $reference?->getKey(),
        ]);
    }
}
