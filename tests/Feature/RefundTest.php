<?php

use Larasell\Larasell\Contracts\PaymentProvider;
use Larasell\Larasell\Contracts\RefundProvider;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Enums\RefundStatus;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Payment;
use Larasell\Larasell\Payments\PaymentRequest;
use Larasell\Larasell\Payments\PaymentResult;
use Larasell\Larasell\Price;
use Larasell\Larasell\Refunds\RefundRequest;
use Larasell\Larasell\Refunds\RefundResult;

class SuccessfulRefundProvider implements PaymentProvider, RefundProvider
{
    public function initiate(PaymentRequest $request): PaymentResult
    {
        return PaymentResult::pending();
    }

    public function refund(RefundRequest $request): RefundResult
    {
        return RefundResult::succeeded('refund-'.$request->refund->id);
    }
}

class PaymentsOnlyProvider implements PaymentProvider
{
    public function initiate(PaymentRequest $request): PaymentResult
    {
        return PaymentResult::pending();
    }
}

/** @return array{Order, Payment} */
function refundablePayment(string $number = 'REFUND', string $method = 'cash'): array
{
    $order = Order::query()->create([
        'number' => $number,
        'currency' => Currency::EUR,
        'customer_email' => 'refund@example.com',
        'customer_name' => 'Refund Customer',
        'status' => OrderStatus::Paid,
        'subtotal' => Price::of(1000),
        'total' => Price::of(1000),
    ]);
    $payment = $order->payments()->create([
        'method' => $method,
        'provider' => $method,
        'status' => PaymentStatus::Succeeded,
        'amount' => Price::of(1000),
        'paid_at' => now(),
    ]);

    return [$order, $payment];
}

it('creates and manually completes partial offline refunds', function () {
    [, $payment] = refundablePayment();

    $refund = $payment->refund(Price::of(400));

    expect($refund->status)->toBe(RefundStatus::Pending)
        ->and($refund->amount->amount())->toBe('400')
        ->and($payment->pendingRefundAmount()->amount())->toBe('400')
        ->and($payment->refundableAmount()->amount())->toBe('600');

    $refund->markAsSucceeded();

    expect($payment->refundedAmount()->amount())->toBe('400')
        ->and($payment->pendingRefundAmount()->amount())->toBe('0')
        ->and($payment->refundableAmount()->amount())->toBe('600')
        ->and($payment->isPartiallyRefunded())->toBeTrue()
        ->and($payment->isFullyRefunded())->toBeFalse();
});

it('refunds the remaining available amount by default', function () {
    [, $payment] = refundablePayment('REFUND-REMAINDER');
    $payment->refund(Price::of(250))->markAsSucceeded();

    $refund = $payment->refund();

    expect($refund->amount->amount())->toBe('750');
});

it('prevents pending refunds from exceeding the payment amount', function () {
    [, $payment] = refundablePayment('REFUND-LIMIT');
    $payment->refund(Price::of(700));

    expect(fn () => $payment->refund(Price::of(301)))
        ->toThrow(InvalidArgumentException::class, 'Refund amount exceeds the refundable payment amount.');
});

it('only refunds successful payments through capable providers', function () {
    [, $pendingPayment] = refundablePayment('REFUND-PENDING');
    $pendingPayment->update(['status' => PaymentStatus::Pending]);

    expect(fn () => $pendingPayment->refund())
        ->toThrow(InvalidArgumentException::class, 'Payment cannot be refunded from [pending].');

    config()->set('larasell.payments.methods.payments_only', [
        'driver' => 'payments_only',
        'provider' => PaymentsOnlyProvider::class,
    ]);
    [, $unsupportedPayment] = refundablePayment('REFUND-UNSUPPORTED', 'payments_only');

    expect(fn () => $unsupportedPayment->refund())
        ->toThrow(InvalidArgumentException::class, 'Payment method [payments_only] does not support refunds.');
});

it('applies synchronous provider refund results', function () {
    config()->set('larasell.payments.methods.synchronous', [
        'driver' => 'synchronous',
        'provider' => SuccessfulRefundProvider::class,
    ]);
    [, $payment] = refundablePayment('REFUND-SYNC', 'synchronous');

    $refund = $payment->refund(Price::of(1000));

    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->reference)->toBe('refund-'.$refund->id)
        ->and($refund->succeeded_at)->not->toBeNull()
        ->and($payment->isFullyRefunded())->toBeTrue();
});

it('only allows paid orders to be cancelled after a full successful refund', function () {
    [$order, $payment] = refundablePayment('REFUND-CANCEL');
    $refund = $payment->refund();

    expect(fn () => $order->cancel())
        ->toThrow(InvalidArgumentException::class, 'An order with a successful payment cannot be cancelled before it is refunded.');

    $refund->markAsSucceeded();

    expect($order->cancel()->status)->toBe(OrderStatus::Cancelled);
});

it('does not cancel fulfilled orders after a full refund', function () {
    [$order, $payment] = refundablePayment('REFUND-FULFILLED');
    $order->transitionTo(OrderStatus::Fulfilled);
    $payment->refund()->markAsSucceeded();

    expect(fn () => $order->cancel())
        ->toThrow(InvalidArgumentException::class, 'Order cannot be cancelled from [fulfilled].');
});
