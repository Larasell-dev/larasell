<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Larasell\Larasell\Casts\PriceCast;
use Larasell\Larasell\Contracts\RefundProvider;
use Larasell\Larasell\Enums\InventoryReservationStatus;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Enums\PromotionRedemptionStatus;
use Larasell\Larasell\Enums\RefundStatus;
use Larasell\Larasell\Events\InventoryReservationConsumed;
use Larasell\Larasell\Events\PaymentCancelled;
use Larasell\Larasell\Events\PaymentFailed;
use Larasell\Larasell\Events\PaymentPending;
use Larasell\Larasell\Events\PaymentSucceeded;
use Larasell\Larasell\Payments\PaymentManager;
use Larasell\Larasell\Price;
use Larasell\Larasell\Refunds\RefundRequest;

/**
 * @property string $method
 * @property string $provider
 * @property string|null $reference
 * @property PaymentStatus $status
 * @property Price $amount
 * @property string|null $failure_message
 * @property Carbon|null $paid_at
 * @property Collection<int, Refund> $refunds
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

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(app(ModelRegistry::class)->refund->class());
    }

    /** @param array<string, mixed> $options */
    public function refund(?Price $amount = null, array $options = []): Refund
    {
        $method = app(PaymentManager::class)->method($this->method);
        $provider = app(PaymentManager::class)->provider($method);

        if (! $provider instanceof RefundProvider) {
            throw new InvalidArgumentException("Payment method [{$this->method}] does not support refunds.");
        }

        $refund = $this->getConnection()->transaction(function () use ($amount): Refund {
            /** @var self $payment */
            $payment = $this->newQuery()->lockForUpdate()->findOrFail($this->getKey());

            if ($payment->status !== PaymentStatus::Succeeded) {
                throw new InvalidArgumentException("Payment cannot be refunded from [{$payment->status->value}].");
            }

            $available = $payment->refundableAmount();
            $amount ??= $available;

            if (! $amount->isPositive()) {
                throw new InvalidArgumentException('Refund amount must be greater than zero.');
            }

            if ($amount->greaterThan($available)) {
                throw new InvalidArgumentException('Refund amount exceeds the refundable payment amount.');
            }

            /** @var Refund $refund */
            $refund = $payment->refunds()->create([
                'provider' => $payment->provider,
                'status' => RefundStatus::Pending,
                'amount' => $amount,
            ]);

            return $refund;
        });

        $result = $provider->refund(new RefundRequest($this->refresh(), $refund, $options));

        if ($result->reference !== null) {
            $refund->update(['reference' => $result->reference]);
        }

        return match ($result->status) {
            RefundStatus::Pending => $refund->refresh(),
            RefundStatus::Succeeded => $refund->markAsSucceeded(),
            RefundStatus::Failed => $refund->markAsFailed($result->failureMessage),
            RefundStatus::Cancelled => $refund->cancel(),
        };
    }

    public function refundedAmount(): Price
    {
        return $this->refundAmountFor([RefundStatus::Succeeded]);
    }

    public function pendingRefundAmount(): Price
    {
        return $this->refundAmountFor([RefundStatus::Pending]);
    }

    public function refundableAmount(): Price
    {
        return $this->amount
            ->subtract($this->refundedAmount())
            ->subtract($this->pendingRefundAmount());
    }

    public function isPartiallyRefunded(): bool
    {
        $refunded = $this->refundedAmount();

        return $refunded->isPositive() && $this->amount->greaterThan($refunded);
    }

    public function isFullyRefunded(): bool
    {
        return ! $this->amount->greaterThan($this->refundedAmount());
    }

    public static function findByProviderReference(string $provider, string $reference): static
    {
        return static::query()
            ->where('provider', $provider)
            ->where('reference', $reference)
            ->firstOrFail();
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
            $this->redeemPromotionRedemptions($order);
            $reservations = $order->inventoryReservations()
                ->where('status', InventoryReservationStatus::Active->value)
                ->get();

            foreach ($reservations as $reservation) {
                $reservation->update([
                    'status' => InventoryReservationStatus::Consumed,
                    'consumed_at' => now(),
                ]);
                InventoryReservationConsumed::dispatch($reservation);
            }
            PaymentSucceeded::dispatch($payment);
            $order->transitionTo(OrderStatus::Paid);

            return $payment->refresh();
        });
    }

    public function markAsFailed(?string $message = null): self
    {
        return $this->getConnection()->transaction(function () use ($message): self {
            $order = $this->order()->lockForUpdate()->firstOrFail();

            /** @var self $payment */
            $payment = $this->newQuery()->lockForUpdate()->findOrFail($this->getKey());

            if ($payment->status === PaymentStatus::Failed) {
                return $payment;
            }

            if ($payment->status !== PaymentStatus::Pending) {
                throw new InvalidArgumentException("Payment cannot be marked as failed from [{$payment->status->value}].");
            }

            if ($order->status !== OrderStatus::PendingPayment) {
                throw new InvalidArgumentException("Payment cannot be marked as failed for an order with status [{$order->status->value}].");
            }

            $payment->update([
                'status' => PaymentStatus::Failed,
                'failure_message' => $message,
            ]);
            PaymentFailed::dispatch($payment);
            $order->transitionTo(OrderStatus::PaymentFailed);

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

    private function redeemPromotionRedemptions(Order $order): void
    {
        $redemptions = $order->promotionRedemptions()
            ->where('status', PromotionRedemptionStatus::Reserved->value)
            ->orderBy('promotion_identifier')
            ->lockForUpdate()
            ->get();
        $redeemedAt = now();

        foreach ($redemptions as $redemption) {
            $counter = $this->getConnection()
                ->table('larasell_promotion_redemption_counters')
                ->where('promotion_identifier', $redemption->promotion_identifier)
                ->lockForUpdate()
                ->first();

            if ($counter === null || $counter->reserved_count < 1) {
                throw new InvalidArgumentException(
                    "Promotion [{$redemption->promotion_identifier}] has inconsistent redemption capacity."
                );
            }

            $this->getConnection()
                ->table('larasell_promotion_redemption_counters')
                ->where('promotion_identifier', $redemption->promotion_identifier)
                ->update([
                    'reserved_count' => $counter->reserved_count - 1,
                    'redeemed_count' => $counter->redeemed_count + 1,
                    'updated_at' => $redeemedAt,
                ]);

            $redemption->update([
                'status' => PromotionRedemptionStatus::Redeemed,
                'redeemed_at' => $redeemedAt,
            ]);
        }
    }

    /** @param array<int, RefundStatus> $statuses */
    private function refundAmountFor(array $statuses): Price
    {
        return $this->refunds()
            ->whereIn('status', array_map(fn (RefundStatus $status): string => $status->value, $statuses))
            ->get()
            ->reduce(
                fn (Price $total, Refund $refund): Price => $total->add($refund->amount),
                Price::of(0),
            );
    }
}
