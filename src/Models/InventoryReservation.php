<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Larasell\Larasell\Enums\InventoryReservationStatus;

/**
 * @property int $order_id
 * @property int $order_item_id
 * @property int $product_id
 * @property int $quantity
 * @property InventoryReservationStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $consumed_at
 * @property Carbon|null $released_at
 * @property string|null $release_reason
 */
class InventoryReservation extends Model
{
    use HasFactory;

    protected $table = 'larasell_inventory_reservations';

    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'status' => InventoryReservationStatus::class,
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(app(ModelRegistry::class)->order->class());
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(app(ModelRegistry::class)->orderItem->class());
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(app(ModelRegistry::class)->product->class());
    }
}
