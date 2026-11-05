<?php

namespace Larasell\Larasell\Promotions;

use Larasell\Larasell\Checkout\OrderId;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Enums\PromotionRedemptionStatus;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\PromotionRedemption;

final readonly class ReleaseExpiredPromotionRedemptionsForOrder
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

            if ($payments->contains(fn ($payment): bool => $payment->status === PaymentStatus::Succeeded)) {
                return false;
            }

            $redemptions = $order->promotionRedemptions()
                ->where('status', PromotionRedemptionStatus::Reserved->value)
                ->orderBy('promotion_identifier')
                ->lockForUpdate()
                ->get();

            if (! $redemptions->contains(
                fn (PromotionRedemption $redemption): bool => $redemption->expires_at?->lessThanOrEqualTo(now()) ?? false
            )) {
                return false;
            }

            $order->cancel(reason: 'Promotion redemption expired');

            return true;
        });
    }
}
