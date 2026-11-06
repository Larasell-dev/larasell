<?php

namespace Larasell\Larasell\Carts\Listeners;

use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\SalesChannels\Events\SalesChannelDeleted;
use Larasell\Larasell\SalesChannels\SalesChannelInUse;

/** Refuses to delete a sales channel that carts were made in. Runs inside the delete's transaction. */
final class KeepChannelsWithCarts
{
    public function handle(SalesChannelDeleted $event): void
    {
        $count = Cart::query()->where('sales_channel_id', $event->channelId->value)->count();

        if ($count > 0) {
            throw SalesChannelInUse::carts($event->channelId, $count);
        }
    }
}
