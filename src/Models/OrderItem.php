<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Larasell\Larasell\Casts\PriceCast;
use Larasell\Larasell\Price;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property string $product_name
 * @property string|null $product_slug
 * @property string|null $product_sku
 * @property string|null $product_barcode
 * @property Price $unit_price
 * @property int $quantity
 * @property int $inventory_quantity
 * @property Price $discount_total
 * @property Price $total
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $table = 'larasell_order_items';

    protected $guarded = [];

    protected $attributes = [
        'discount_total' => '{"amount":"0"}',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'quantity' => 'integer',
        'inventory_quantity' => 'integer',
        'unit_price' => PriceCast::class,
        'discount_total' => PriceCast::class,
        'total' => PriceCast::class,
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(app(ModelRegistry::class)->order->class());
    }

    /** @return HasOne<InventoryReservation, $this> */
    public function inventoryReservation(): HasOne
    {
        return $this->hasOne(app(ModelRegistry::class)->inventoryReservation->class());
    }
}
