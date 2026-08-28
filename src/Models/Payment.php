<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Larasell\Larasell\Casts\PriceCast;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Events\PaymentCancelled;
use Larasell\Larasell\Events\PaymentFailed;
use Larasell\Larasell\Events\PaymentPending;
use Larasell\Larasell\Events\PaymentSucceeded;
use Larasell\Larasell\Price;

/**
 * @property string $method
 * @property string $provider
 * @property string|null $reference
 * @property PaymentStatus $status
 * @property Price $amount
 * @property string|null $failure_message
 * @property Carbon|null $paid_at
 */
class Payment extends Model
{
    use HasFactory;

    protected $table = 'larasell_payments';

    protected $guarded = [];

    protected $casts = [
        'amount' => PriceCast::class,
        'status' => PaymentStatus::class,
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (self $payment): void {
            self::dispatchStatusEvent($payment);
        });
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(app(ModelRegistry::class)->order->class());
    }

    public function transitionTo(PaymentStatus $status): void
    {
        if (! $this->status->canTransitionTo($status)) {
            throw new InvalidArgumentException("Payment cannot transition from [{$this->status->value}] to [{$status->value}].");
        }

        $this->update(['status' => $status]);

        self::dispatchStatusEvent($this);
    }

    public function markAsPaid(): self
    {
        return $this->getConnection()->transaction(function (): self {
            $order = $this->order()->lockForUpdate()->firstOrFail();

            /** @var self $payment */
            $payment = $this->newQuery()->lockForUpdate()->findOrFail($this->getKey());

            if ($payment->status === PaymentStatus::Succeeded) {
                return $payment;
            }

            if ($payment->status !== PaymentStatus::Pending) {
                throw new InvalidArgumentException("Payment cannot be marked as paid from [{$payment->status->value}].");
            }

            if ($order->status !== OrderStatus::PendingPayment) {
                throw new InvalidArgumentException("Payment cannot be marked as paid for an order with status [{$order->status->value}].");
            }

            $payment->update([
                'status' => PaymentStatus::Succeeded,
                'paid_at' => now(),
                'failure_message' => null,
            ]);
            PaymentSucceeded::dispatch($payment);
            $order->transitionTo(OrderStatus::Paid);

            return $payment->refresh();
        });
    }

    public function cancel(): self
    {
        return $this->getConnection()->transaction(function (): self {
            /** @var self $payment */
            $payment = $this->newQuery()->lockForUpdate()->findOrFail($this->getKey());

            if ($payment->status === PaymentStatus::Cancelled) {
                return $payment;
            }

            if ($payment->status !== PaymentStatus::Pending) {
                throw new InvalidArgumentException("Payment cannot be cancelled from [{$payment->status->value}].");
            }

            $payment->transitionTo(PaymentStatus::Cancelled);

            return $payment->refresh();
        });
    }

    private static function dispatchStatusEvent(self $payment): void
    {
        match ($payment->status) {
            PaymentStatus::Pending => PaymentPending::dispatch($payment),
            PaymentStatus::Succeeded => PaymentSucceeded::dispatch($payment),
            PaymentStatus::Failed => PaymentFailed::dispatch($payment),
            PaymentStatus::Cancelled => PaymentCancelled::dispatch($payment),
        };
    }
}
