<?php

namespace Larasell\Larasell\Carts\Strategies\Concerns;

use Larasell\Larasell\Carts\CartMergeContext;
use Larasell\Larasell\Carts\CartMergeLineIntent;
use Larasell\Larasell\Models\CartItem;

trait BuildsLineIntents
{
    /**
     * @return list<CartMergeLineIntent>
     */
    private function sourceLineIntents(CartMergeContext $context): array
    {
        $intents = $context->sourceItems()
            ->map(fn (CartItem $item): CartMergeLineIntent => new CartMergeLineIntent(
                variant: $item->variant,
                quantity: $item->quantity,
                metadata: $item->metadata->all(),
            ))
            ->values()
            ->all();

        return array_values($intents);
    }
}
