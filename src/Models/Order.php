<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use Larasell\Larasell\Address;
use Larasell\Larasell\Casts\AddressCast;
use Larasell\Larasell\Casts\PriceCast;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\OrderStatus;
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
 * @property Price $subtotal
 * @property Price $total
 * @property string|null $shipping_method
 * @property string|null $shipping_option
 * @property string|null $shipping_option_name
 * @property Price|null $shipping_price
 * @property Collection<int, OrderItem> $items
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
        'subtotal' => PriceCast::class,
        'total' => PriceCast::class,
        'shipping_price' => PriceCast::class,
    ];

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(app(ModelRegistry::class)->orderItem->class());
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(app(ModelRegistry::class)->payment->class());
    }

    public function transitionTo(OrderStatus $status): void
    {
        if (! $this->status->canTransitionTo($status)) {
            throw new InvalidArgumentException("Order cannot transition from [{$this->status->value}] to [{$status->value}].");
        }

        $this->update(['status' => $status]);
    }
}
