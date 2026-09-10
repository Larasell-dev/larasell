<?php

namespace Larasell\Larasell\Carts\Strategies;

use Larasell\Larasell\Carts\CartMergeAttributePlan;
use Larasell\Larasell\Carts\CartMergeContext;
use Larasell\Larasell\Carts\CartMergeLineIntent;
use Larasell\Larasell\Carts\CartMergeLinePlan;
use Larasell\Larasell\Carts\CartMergeStrategy;
use Larasell\Larasell\Models\CartItem;

final class CombineQuantities implements CartMergeStrategy
{
    public function lines(CartMergeContext $context): CartMergeLinePlan
    {
        $intents = $context->sourceItems()
            ->map(function (CartItem $item) use ($context): CartMergeLineIntent {
                $destination = $context->matchingDestinationItem($item);
                $destinationQuantity = $destination instanceof CartItem ? $destination->quantity : 0;

                return new CartMergeLineIntent(
                    variant: $item->variant,
                    quantity: $item->quantity + $destinationQuantity,
                    metadata: $item->metadata->all(),
                );
            })
            ->values()
            ->all();

        return new CartMergeLinePlan(array_values($intents));
    }

    public function attributes(CartMergeContext $context): CartMergeAttributePlan
    {
        return CartMergeAttributePlan::preferDestination($context);
    }
}
