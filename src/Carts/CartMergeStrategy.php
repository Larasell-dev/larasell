<?php

namespace Larasell\Larasell\Carts;

interface CartMergeStrategy
{
    public function lines(CartMergeContext $context): CartMergeLinePlan;

    public function attributes(CartMergeContext $context): CartMergeAttributePlan;
}
