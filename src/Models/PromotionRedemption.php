<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Larasell\Larasell\Enums\PromotionRedemptionStatus;

/**
 * @property int $id
 * @property int $order_id
 * @property string $promotion_identifier
 * @property string|null $customer_identifier
 * @property int|null $global_limit
 * @property int|null $customer_limit
 * @property PromotionRedemptionStatus $status
 * @property Carbon|null $expires_at
 * @property Carbon|null $redeemed_at
 * @property Carbon|null $released_at
 * @property Order $order
 */
class PromotionRedemption extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'larasell_promotion_redemptions';

    protected $guarded = [];

    protected $casts = [
        'global_limit' => 'integer',
        'customer_limit' => 'integer',
        'status' => PromotionRedemptionStatus::class,
        'expires_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(app(ModelRegistry::class)->order->class());
    }
}
