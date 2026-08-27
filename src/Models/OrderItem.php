<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Larasell\Larasell\Casts\PriceCast;
use Larasell\Larasell\Price;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property string $product_name
 * @property string|null $product_slug
 * @property Price $unit_price
 * @property int $quantity
 * @property Price $total
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $table = 'larasell_order_items';

    protected $guarded = [];

    protected $casts = [
        'product_id' => 'integer',
        'quantity' => 'integer',
        'unit_price' => PriceCast::class,
        'total' => PriceCast::class,
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(app(ModelRegistry::class)->order->class());
    }
}
