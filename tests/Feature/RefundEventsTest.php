<?php

use Illuminate\Support\Facades\Event;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Events\RefundCancelled;
use Larasell\Larasell\Events\RefundFailed;
use Larasell\Larasell\Events\RefundPending;
use Larasell\Larasell\Events\RefundSucceeded;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Payment;
use Larasell\Larasell\Price;

/** @return array<class-string> */
function refundEvents(): array
{
    return [
        RefundPending::class,
        RefundSucceeded::class,
        RefundFailed::class,
        RefundCancelled::class,
    ];
}

function refundEventPayment(string $number): Payment
{
    $order = Order::query()->create([
        'number' => $number,
        'currency' => Currency::EUR,
        'customer_email' => 'refund-events@example.com',
        'customer_name' => 'Refund Events Customer',
        'status' => OrderStatus::Paid,
        'subtotal' => Price::of(1000),
        'total' => Price::of(1000),
    ]);

    return $order->payments()->create([
        'method' => 'cash',
        'provider' => 'offline',
        'status' => PaymentStatus::Succeeded,
        'amount' => Price::of(1000),
        'paid_at' => now(),
    ]);
}

it('dispatches pending and succeeded refund events once', function () {
    Event::fake(refundEvents());
    $payment = refundEventPayment('REFUND-EVENT-SUCCEEDED');

    $refund = $payment->refund();
    $refund->markAsSucceeded();
    $refund->markAsSucceeded();

    Event::assertDispatchedTimes(RefundPending::class, 1);
    Event::assertDispatchedTimes(RefundSucceeded::class, 1);
});

it('dispatches failed and cancelled refund events once', function () {
    Event::fake(refundEvents());
    $failedPayment = refundEventPayment('REFUND-EVENT-FAILED');
    $cancelledPayment = refundEventPayment('REFUND-EVENT-CANCELLED');

    $failed = $failedPayment->refund();
    $failed->markAsFailed('Rejected.');
    $failed->markAsFailed('Rejected.');

    $cancelled = $cancelledPayment->refund();
    $cancelled->cancel();
    $cancelled->cancel();

    Event::assertDispatchedTimes(RefundFailed::class, 1);
    Event::assertDispatchedTimes(RefundCancelled::class, 1);
});
