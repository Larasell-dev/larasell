<?php

namespace Larasell\Larasell\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case PaymentFailed = 'payment_failed';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, match ($this) {
            self::PendingPayment => [self::Paid, self::PaymentFailed, self::Cancelled],
            self::PaymentFailed => [self::PendingPayment, self::Cancelled],
            self::Paid => [self::Fulfilled, self::Cancelled],
            self::Fulfilled, self::Cancelled => [],
        }, true);
    }
}
