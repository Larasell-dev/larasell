<?php

use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Payment;
use Larasell\Larasell\Price;

function offlinePaymentOrder(): Order
{
    return Order::query()->create([
        'number' => 'OFFLINE-TEST',
        'currency' => Currency::EUR,
        'customer_email' => 'buyer@example.com',
        'customer_name' => 'Buyer',
        'billing_address' => null,
        'shipping_address' => null,
        'status' => OrderStatus::PendingPayment,
        'subtotal' => Price::of(1000),
        'total' => Price::of(1000),
    ]);
}

function offlinePayment(Order $order, PaymentStatus $status = PaymentStatus::Pending): Payment
{
    return $order->payments()->create([
        'method' => 'cash',
        'provider' => 'offline',
        'status' => $status,
        'amount' => $order->total,
    ]);
}

it('marks an offline payment and its order as paid', function () {
    $order = offlinePaymentOrder();
    $payment = offlinePayment($order);

    $paid = $payment->markAsPaid();

    expect($paid->status)->toBe(PaymentStatus::Succeeded)
        ->and($paid->paid_at)->not->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('marks a payment as paid idempotently', function () {
    $order = offlinePaymentOrder();
    $payment = offlinePayment($order);

    $first = $payment->markAsPaid();
    $paidAt = $first->paid_at->toISOString();
    $second = $payment->markAsPaid();

    expect($second->status)->toBe(PaymentStatus::Succeeded)
        ->and($second->paid_at->toISOString())->toBe($paidAt)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('cancels a pending payment idempotently without cancelling the order', function () {
    $order = offlinePaymentOrder();
    $payment = offlinePayment($order);

    $cancelled = $payment->cancel();
    $cancelledAgain = $payment->cancel();

    expect($cancelled->status)->toBe(PaymentStatus::Cancelled)
        ->and($cancelledAgain->status)->toBe(PaymentStatus::Cancelled)
        ->and($order->fresh()->status)->toBe(OrderStatus::PendingPayment);
});

it('does not mark a cancelled payment as paid', function () {
    $payment = offlinePayment(offlinePaymentOrder(), PaymentStatus::Cancelled);

    expect(fn () => $payment->markAsPaid())
        ->toThrow(InvalidArgumentException::class, 'Payment cannot be marked as paid from [cancelled].');
});

it('does not cancel a succeeded payment', function () {
    $payment = offlinePayment(offlinePaymentOrder(), PaymentStatus::Succeeded);

    expect(fn () => $payment->cancel())
        ->toThrow(InvalidArgumentException::class, 'Payment cannot be cancelled from [succeeded].');
});

it('marks a pending payment and its order as failed', function () {
    $order = offlinePaymentOrder();
    $payment = offlinePayment($order);

    $failed = $payment->markAsFailed('Provider declined the payment.');
    $failedAgain = $payment->markAsFailed('Ignored duplicate message.');

    expect($failed->status)->toBe(PaymentStatus::Failed)
        ->and($failed->failure_message)->toBe('Provider declined the payment.')
        ->and($failedAgain->failure_message)->toBe('Provider declined the payment.')
        ->and($order->fresh()->status)->toBe(OrderStatus::PaymentFailed);
});
