<?php

namespace Larasell\Larasell\Inventory;

use Larasell\Larasell\Enums\InventoryReservationStatus;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Models\InventoryReservation;
use Larasell\Larasell\Models\ModelRegistry;
use Larasell\Larasell\Models\Order;

final readonly class ReleaseExpiredInventoryForOrder
{
    public function __construct(private ModelRegistry $models) {}

    public function handle(int $orderId): bool
    {
        $orderModel = $this->models->order->new();

        return $orderModel->getConnection()->transaction(function () use ($orderId): bool {
            /** @var Order|null $order */
            $order = $this->models->order->query()
                ->lockForUpdate()
                ->find($orderId);

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
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get();

            if (! $reservations->contains(
                fn (InventoryReservation $reservation): bool => $reservation->expires_at?->lessThanOrEqualTo(now()) ?? false
            )) {
                return false;
            }

            $reservationIds = $reservations->modelKeys();

            $order->cancel(reason: 'Inventory reservation expired');

            $this->models->inventoryReservation->query()
                ->whereKey($reservationIds)
                ->update(['release_reason' => 'reservation_expired']);

            return true;
        });
    }
}
