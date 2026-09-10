<?php

namespace Larasell\Larasell\Carts;

final readonly class CartMergePlan
{
    public function __construct(
        public CartMergeLinePlan $lines,
        public CartMergeAttributePlan $attributes,
    ) {}
}
