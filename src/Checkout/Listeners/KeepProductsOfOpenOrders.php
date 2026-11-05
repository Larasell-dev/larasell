<?php

namespace Larasell\Larasell\Checkout\Listeners;

use Larasell\Larasell\Catalog\Events\ProductDeleted;
use Larasell\Larasell\Catalog\Exceptions\ProductInUse;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Models\OrderItem;

/** Refuses to delete a product while an order with it is still open. Runs inside the delete's transaction. */
final class KeepProductsOfOpenOrders
{
    public function handle(ProductDeleted $event): void
    {
        $orderIds = array_values(OrderItem::query()
            ->where('product_id', $event->productId)
            ->whereHas('order', fn ($query) => $query->whereIn('status', [
                OrderStatus::PendingPayment->value,
                OrderStatus::PaymentFailed->value,
                OrderStatus::Paid->value,
            ]))
            ->orderBy('order_id')
            ->distinct()
            ->pluck('order_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());

        if ($orderIds !== []) {
            throw ProductInUse::openOrders($event->productId, $orderIds);
        }
    }
}
