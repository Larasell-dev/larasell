<?php

namespace Larasell\Larasell\Carts\Strategies;

use Closure;
use Larasell\Larasell\Carts\CartMergeAttributePlan;
use Larasell\Larasell\Carts\CartMergeContext;
use Larasell\Larasell\Carts\CartMergeLinePlan;
use Larasell\Larasell\Carts\CartMergePlan;
use Larasell\Larasell\Carts\CartMergeStrategy;

final class CallbackCartMergeStrategy implements CartMergeStrategy
{
    private ?CartMergePlan $plan = null;

    public function __construct(
        private readonly Closure $callback,
    ) {}

    public function lines(CartMergeContext $context): CartMergeLinePlan
    {
        return $this->resolve($context)->lines;
    }

    public function attributes(CartMergeContext $context): CartMergeAttributePlan
    {
        return $this->resolve($context)->attributes;
    }

    private function resolve(CartMergeContext $context): CartMergePlan
    {
        if ($this->plan !== null) {
            return $this->plan;
        }

        return $this->plan = ($this->callback)($context);
    }
}
