<?php

namespace Larasell\Larasell\Carts\Strategies;

use Larasell\Larasell\Carts\CartMergeAttributePlan;
use Larasell\Larasell\Carts\CartMergeContext;
use Larasell\Larasell\Carts\CartMergeLinePlan;
use Larasell\Larasell\Carts\CartMergeStrategy;
use Larasell\Larasell\Carts\Strategies\Concerns\BuildsLineIntents;

final class ReplaceDestination implements CartMergeStrategy
{
    use BuildsLineIntents;

    public function lines(CartMergeContext $context): CartMergeLinePlan
    {
        return new CartMergeLinePlan(
            intents: $this->sourceLineIntents($context),
            replaceDestinationItems: true,
        );
    }

    public function attributes(CartMergeContext $context): CartMergeAttributePlan
    {
        return CartMergeAttributePlan::preferSource($context);
    }
}
