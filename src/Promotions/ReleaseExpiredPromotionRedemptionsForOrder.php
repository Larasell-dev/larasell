<?php

namespace Larasell\Larasell\Promotions;

use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Enums\PromotionRedemptionStatus;
use Larasell\Larasell\Models\ModelRegistry;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\PromotionRedemption;

final readonly class ReleaseExpiredPromotionRedemptionsForOrder
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
