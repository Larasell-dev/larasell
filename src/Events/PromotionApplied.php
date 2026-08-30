<?php

namespace Larasell\Larasell\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Larasell\Larasell\Models\Order;

class PromotionApplied implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /** @param array<string, mixed> $discount */
    public function __construct(
        public readonly Order $order,
        public readonly array $discount,
    ) {}
}
