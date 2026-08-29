<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Larasell\Larasell\Address;
use Larasell\Larasell\Casts\AddressCast;
use Larasell\Larasell\Casts\PriceCast;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\InventoryReservationReleaseReason;
use Larasell\Larasell\Enums\InventoryReservationStatus;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Events\InventoryReservationReleased;
use Larasell\Larasell\Events\InventoryRestocked;
use Larasell\Larasell\Events\OrderCancelled;
use Larasell\Larasell\Events\OrderFulfilled;
use Larasell\Larasell\Events\OrderPaid;
use Larasell\Larasell\Price;

/**
 * @property int $id
 * @property string $number
 * @property Currency $currency
 * @property int|null $customer_id
 * @property string $customer_email
 * @property string $customer_name
 * @property string|null $customer_phone
 * @property Address|null $billing_address
 * @property Address|null $shipping_address
 * @property OrderStatus $status
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property Carbon|null $inventory_restocked_at
 * @property Price $subtotal
 * @property Price $total
 * @property string|null $shipping_method
 * @property string|null $shipping_option
 * @property string|null $shipping_option_name
 * @property Price|null $shipping_price
 * @property Collection<int, OrderItem> $items
 * @property Collection<int, InventoryReservation> $inventoryReservations
 * @property Collection<int, Payment> $payments
 */
class Order extends Model
{
    use HasFactory;

    protected $table = 'larasell_orders';

    protected $guarded = [];

    protected $casts = [
        'customer_id' => 'integer',
        'currency' => Currency::class,
        'billing_address' => AddressCast::class,
        'shipping_address' => AddressCast::class,
        'status' => OrderStatus::class,
        'cancelled_at' => 'datetime',
        'inventory_restocked_at' => 'datetime',
        'subtotal' => PriceCast::class,
        'total' => PriceCast::class,
        'shipping_price' => PriceCast::class,
    ];

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(app(ModelRegistry::class)->orderItem->class());
    }

    /** @return HasMany<InventoryReservation, $this> */
    public function inventoryReservations(): HasMany
    {
        return $this->hasMany(app(ModelRegistry::class)->inventoryReservation->class());
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(app(ModelRegistry::class)->payment->class());
    }

    public function transitionTo(OrderStatus $status): void
    {
        if ($status === OrderStatus::Cancelled) {
            $this->cancel();

            return;
        }

        if (! $this->status->canTransitionTo($status)) {
            throw new InvalidArgumentException("Order cannot transition from [{$this->status->value}] to [{$status->value}].");
        }

        $this->update(['status' => $status]);

        match ($status) {
            OrderStatus::Paid => OrderPaid::dispatch($this),
            OrderStatus::Fulfilled => OrderFulfilled::dispatch($this),
            default => null,
        };
    }

    public function cancel(
        bool $restock = true,
        ?string $reason = null,
        InventoryReservationReleaseReason $inventoryReleaseReason = InventoryReservationReleaseReason::OrderCancelled,
    ): self {
        return $this->getConnection()->transaction(function () use ($restock, $reason, $inventoryReleaseReason): self {
            /** @var self $order */
            $order = $this->newQuery()->lockForUpdate()->findOrFail($this->getKey());

            if ($order->status === OrderStatus::Cancelled) {
                return $order;
            }

            if (! in_array($order->status, [OrderStatus::PendingPayment, OrderStatus::PaymentFailed, OrderStatus::Paid], true)) {
                throw new InvalidArgumentException("Order cannot be cancelled from [{$order->status->value}].");
            }

            $payments = $order->payments()->lockForUpdate()->get();

            if ($payments
                ->where('status', PaymentStatus::Succeeded)
                ->contains(fn (Payment $payment): bool => ! $payment->isFullyRefunded())) {
                throw new InvalidArgumentException('An order with a successful payment cannot be cancelled before it is refunded.');
            }

            foreach ($payments->where('status', PaymentStatus::Pending) as $payment) {
                $payment->transitionTo(PaymentStatus::Cancelled);
            }

            $restockedAt = null;
            $reservations = $order->inventoryReservations()
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get();

            if ($restock) {
                foreach ($reservations as $reservation) {
                    $product = app(ModelRegistry::class)->product->query()
                        ->lockForUpdate()
                        ->find($reservation->product_id);

                    if ($product !== null && $product->stock !== null) {
                        $product->increment('stock', $reservation->quantity);
                        InventoryRestocked::dispatch($product, $order, $reservation->quantity);
                    }
                }

                $items = $order->items()
                    ->where('inventory_quantity', '>', 0)
                    ->whereDoesntHave('inventoryReservation')
                    ->get();

                foreach ($items->sortBy('product_id') as $item) {
                    if ($item->product_id === null) {
                        continue;
                    }

                    $product = app(ModelRegistry::class)->product->query()
                        ->lockForUpdate()
                        ->find($item->product_id);

                    if ($product !== null && $product->stock !== null) {
                        $product->increment('stock', $item->inventory_quantity);
                        InventoryRestocked::dispatch($product, $order, $item->inventory_quantity);
                    }
                }

                $restockedAt = now();
            }

            foreach ($reservations->where('status', InventoryReservationStatus::Active) as $reservation) {
                $reservation->update([
                    'status' => InventoryReservationStatus::Released,
                    'released_at' => now(),
                    'release_reason' => $inventoryReleaseReason->value,
                ]);
                InventoryReservationReleased::dispatch($reservation);
            }

            $order->update([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'inventory_restocked_at' => $restockedAt,
            ]);

            OrderCancelled::dispatch($order);

            return $order->refresh();
        });
    }
}
