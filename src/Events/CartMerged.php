<?php

namespace Larasell\Larasell\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Larasell\Larasell\Carts\CartMergeLineAdjustment;
use Larasell\Larasell\Models\Cart;

class CartMerged implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<CartMergeLineAdjustment>  $skippedLines
     * @param  list<CartMergeLineAdjustment>  $adjustedLines
     * @param  list<string>  $droppedPromotionCodes
     */
    public function __construct(
        public readonly Cart $cart,
        public readonly int $sourceCartId,
        public readonly string $strategy,
        public readonly array $skippedLines = [],
        public readonly array $adjustedLines = [],
        public readonly array $droppedPromotionCodes = [],
    ) {}
}
