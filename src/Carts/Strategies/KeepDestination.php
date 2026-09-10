<?php

namespace Larasell\Larasell\Carts\Strategies;

use Larasell\Larasell\Carts\CartMergeAttributePlan;
use Larasell\Larasell\Carts\CartMergeContext;
use Larasell\Larasell\Carts\CartMergeLinePlan;
use Larasell\Larasell\Carts\CartMergeStrategy;

final class KeepDestination implements CartMergeStrategy
{
    public function lines(CartMergeContext $context): CartMergeLinePlan
    {
        return new CartMergeLinePlan([]);
    }

    public function attributes(CartMergeContext $context): CartMergeAttributePlan
    {
        return CartMergeAttributePlan::preferDestination($context);
    }
}
