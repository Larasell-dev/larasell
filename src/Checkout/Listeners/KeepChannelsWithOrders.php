<?php

namespace Larasell\Larasell\Checkout\Listeners;

use Larasell\Larasell\Models\Order;
use Larasell\Larasell\SalesChannels\Events\SalesChannelDeleted;
use Larasell\Larasell\SalesChannels\Exceptions\SalesChannelInUse;

/** Refuses to delete a sales channel that orders were made in, whatever their status. Runs inside the delete's transaction. */
final class KeepChannelsWithOrders
{
    public function handle(SalesChannelDeleted $event): void
    {
        $count = Order::query()->where('sales_channel_id', $event->channelId->value)->count();

        if ($count > 0) {
            throw SalesChannelInUse::orders($event->channelId, $count);
        }
    }
}
