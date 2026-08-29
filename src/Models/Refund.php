<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Larasell\Larasell\Casts\PriceCast;
use Larasell\Larasell\Enums\RefundStatus;
use Larasell\Larasell\Events\RefundCancelled;
use Larasell\Larasell\Events\RefundFailed;
use Larasell\Larasell\Events\RefundPending;
use Larasell\Larasell\Events\RefundSucceeded;
use Larasell\Larasell\Price;

/**
 * @property int $id
 * @property int $payment_id
 * @property string $provider
 * @property string|null $reference
 * @property RefundStatus $status
 * @property Price $amount
 * @property string|null $failure_message
 * @property Carbon|null $succeeded_at
 */
class Refund extends Model
{
    use HasFactory;

    protected $table = 'larasell_refunds';

    protected $guarded = [];

    protected $casts = [
        'amount' => PriceCast::class,
        'status' => RefundStatus::class,
        'succeeded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (self $refund): void {
            self::dispatchStatusEvent($refund);
        });
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(app(ModelRegistry::class)->payment->class());
    }

    public static function findByProviderReference(string $provider, string $reference): static
    {
        return static::query()
            ->where('provider', $provider)
            ->where('reference', $reference)
            ->firstOrFail();
    }

    public function markAsSucceeded(): self
    {
        return $this->transitionTo(RefundStatus::Succeeded);
    }

    public function markAsFailed(?string $message = null): self
    {
        return $this->transitionTo(RefundStatus::Failed, $message);
    }

    public function cancel(): self
    {
        return $this->transitionTo(RefundStatus::Cancelled);
    }

    private function transitionTo(RefundStatus $status, ?string $failureMessage = null): self
    {
        return $this->getConnection()->transaction(function () use ($status, $failureMessage): self {
            /** @var self $refund */
            $refund = $this->newQuery()->lockForUpdate()->findOrFail($this->getKey());

            if ($refund->status === $status) {
                return $refund;
            }

            if (! $refund->status->canTransitionTo($status)) {
                throw new InvalidArgumentException("Refund cannot transition from [{$refund->status->value}] to [{$status->value}].");
            }

            $refund->update([
                'status' => $status,
                'failure_message' => $status === RefundStatus::Failed ? $failureMessage : null,
                'succeeded_at' => $status === RefundStatus::Succeeded ? now() : null,
            ]);
            self::dispatchStatusEvent($refund);

            return $refund->refresh();
        });
    }

    private static function dispatchStatusEvent(self $refund): void
    {
        match ($refund->status) {
            RefundStatus::Pending => RefundPending::dispatch($refund),
            RefundStatus::Succeeded => RefundSucceeded::dispatch($refund),
            RefundStatus::Failed => RefundFailed::dispatch($refund),
            RefundStatus::Cancelled => RefundCancelled::dispatch($refund),
        };
    }
}
