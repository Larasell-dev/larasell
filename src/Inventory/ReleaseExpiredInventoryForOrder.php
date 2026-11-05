<?php

namespace Larasell\Larasell\Inventory;

use Larasell\Larasell\Checkout\OrderId;
use Larasell\Larasell\Enums\InventoryReservationReleaseReason;
use Larasell\Larasell\Enums\InventoryReservationStatus;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Events\InventoryReservationExpired;
use Larasell\Larasell\Models\InventoryReservation;
use Larasell\Larasell\Models\Order;

final readonly class ReleaseExpiredInventoryForOrder
{
    public function handle(OrderId $orderId): bool
    {
        $orderModel = (new Order);

        return $orderModel->getConnection()->transaction(function () use ($orderId): bool {
            /** @var Order|null $order */
            $order = Order::query()
                ->lockForUpdate()
                ->find($orderId->value);

            if ($order === null || $order->status !== OrderStatus::PendingPayment) {
                return false;
            }

            $payments = $order->payments()->lockForUpdate()->get();

            if ($payments->contains(
                fn ($payment): bool => $payment->status === PaymentStatus::Succeeded
            )) {
                return false;
            }

            $reservations = $order->inventoryReservations()
                ->where('status', InventoryReservationStatus::Active->value)
                ->orderBy('product_variant_id')
                ->lockForUpdate()
                ->get();

            if (! $reservations->contains(
                fn (InventoryReservation $reservation): bool => $reservation->expires_at?->lessThanOrEqualTo(now()) ?? false
            )) {
                return false;
            }

            $order->cancel(
                reason: 'Inventory reservation expired',
                inventoryReleaseReason: InventoryReservationReleaseReason::ReservationExpired,
            );

            foreach ($reservations as $reservation) {
                InventoryReservationExpired::dispatch($reservation->refresh());
            }

            return true;
        });
    }
}
