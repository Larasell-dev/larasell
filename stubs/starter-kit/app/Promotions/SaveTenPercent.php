<?php

namespace App\Promotions;

use Larasell\Larasell\Contracts\Promotions\HasCode;
use Larasell\Larasell\Contracts\Promotions\Promotion;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionContext;

final class SaveTenPercent implements HasCode, Promotion
{
    public function code(): string
    {
        return 'SAVE10';
    }

    public function apply(PromotionContext $context): ?DiscountResult
    {
        $allocations = $context->percentageOff(10);

        if ($allocations === []) {
            return null;
        }

        return new DiscountResult(
            identifier: 'save-ten-percent',
            name: 'Save 10%',
            allocations: $allocations,
        );
    }
}
