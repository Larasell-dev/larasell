<?php

namespace Larasell\Larasell\Carts\Strategies;

use Larasell\Larasell\Carts\CartMergeAttributePlan;
use Larasell\Larasell\Carts\CartMergeContext;
use Larasell\Larasell\Carts\CartMergeLinePlan;
use Larasell\Larasell\Carts\CartMergeStrategy;

final readonly class FailOnConflict implements CartMergeStrategy
{
    public function __construct(
        private CartMergeStrategy $strategy = new CombineQuantities,
    ) {}

    public function lines(CartMergeContext $context): CartMergeLinePlan
    {
        $lines = $this->strategy->lines($context);

        return new CartMergeLinePlan(
            intents: $lines->intents,
            replaceDestinationItems: $lines->replaceDestinationItems,
            failOnConflict: true,
        );
    }

    public function attributes(CartMergeContext $context): CartMergeAttributePlan
    {
        return $this->strategy->attributes($context);
    }
}
