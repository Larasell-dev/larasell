<?php

namespace Larasell\Larasell\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Larasell\Larasell\Models\PromotionRedemption;

class PromotionRedemptionRedeemed implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly PromotionRedemption $redemption) {}
}
