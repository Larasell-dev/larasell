<?php

namespace Larasell\Larasell\Contracts;

use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionContext;

interface Promotion
{
    public function apply(PromotionContext $context): ?DiscountResult;
}
