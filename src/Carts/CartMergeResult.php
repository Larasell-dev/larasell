<?php

namespace Larasell\Larasell\Carts;

use Larasell\Larasell\Models\Cart;

final readonly class CartMergeResult
{
    /**
     * @param  list<CartMergeLineAdjustment>  $skippedLines
     * @param  list<CartMergeLineAdjustment>  $adjustedLines
     * @param  list<string>  $droppedPromotionCodes
     */
    public function __construct(
        public Cart $cart,
        public array $skippedLines = [],
        public array $adjustedLines = [],
        public array $droppedPromotionCodes = [],
    ) {}
}
