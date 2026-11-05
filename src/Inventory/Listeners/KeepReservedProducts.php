<?php

namespace Larasell\Larasell\Inventory\Listeners;

use Larasell\Larasell\Catalog\Events\ProductDeleted;
use Larasell\Larasell\Catalog\Exceptions\ProductInUse;
use Larasell\Larasell\Enums\InventoryReservationStatus;
use Larasell\Larasell\Models\InventoryReservation;

/** Refuses to delete a product while units of it are reserved. Runs inside the delete's transaction. */
final class KeepReservedProducts
{
    public function handle(ProductDeleted $event): void
    {
        $reserved = (int) InventoryReservation::query()
            ->where('product_id', $event->productId)
            ->where('status', InventoryReservationStatus::Active->value)
            ->sum('quantity');

        if ($reserved > 0) {
            throw ProductInUse::reservedStock($event->productId, $reserved);
        }
    }
}
