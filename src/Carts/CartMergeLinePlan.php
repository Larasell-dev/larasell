<?php

namespace Larasell\Larasell\Carts;

final readonly class CartMergeLinePlan
{
    /**
     * @param  list<CartMergeLineIntent>  $intents
     */
    public function __construct(
        public array $intents,
        public bool $replaceDestinationItems = false,
        public bool $failOnConflict = false,
    ) {}
}
