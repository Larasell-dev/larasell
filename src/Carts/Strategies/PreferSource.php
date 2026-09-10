<?php

namespace Larasell\Larasell\Carts\Strategies;

use Larasell\Larasell\Carts\CartMergeAttributePlan;
use Larasell\Larasell\Carts\CartMergeContext;
use Larasell\Larasell\Carts\CartMergeLinePlan;
use Larasell\Larasell\Carts\CartMergeStrategy;
use Larasell\Larasell\Carts\Strategies\Concerns\BuildsLineIntents;

final class PreferSource implements CartMergeStrategy
{
    use BuildsLineIntents;

    public function lines(CartMergeContext $context): CartMergeLinePlan
    {
        return new CartMergeLinePlan($this->sourceLineIntents($context));
    }

    public function attributes(CartMergeContext $context): CartMergeAttributePlan
    {
        return CartMergeAttributePlan::preferSource($context);
    }
}
