<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Larasell\Larasell\Casts\PriceCast;
use Larasell\Larasell\Enums\PaymentStatus;

class Payment extends Model
{
    use HasFactory;

    protected $table = 'larasell_payments';

    protected $guarded = [];

    protected $casts = [
        'amount' => PriceCast::class,
        'status' => PaymentStatus::class,
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(app(ModelRegistry::class)->order->class());
    }
}
