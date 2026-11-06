<?php

namespace Larasell\Larasell\SalesChannels\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\SalesChannels\Events\SalesChannelDeleted;
use Larasell\Larasell\SalesChannels\InvalidSalesChannel;
use Larasell\Larasell\SalesChannels\Models\SalesChannel;
use Larasell\Larasell\SalesChannels\SalesChannelId;
use Larasell\Larasell\SalesChannels\SalesChannels;

/**
 * Deletes a sales channel with its locales and currencies; tables of other modules that hang off
 * the channel, such as entry points and SEO URLs, cascade. It refuses the last channel. Other
 * modules may refuse by throwing from a listener of SalesChannelDeleted, e.g.
 * Larasell\Larasell\SalesChannels\SalesChannelInUse for carts or orders, which keep pointing at it.
 */
final readonly class DeleteSalesChannel
{
    public function __construct(
        private ConnectionInterface $db,
        private SalesChannels $channels,
    ) {}

    public function handle(SalesChannelId $channelId): void
    {
        $this->db->transaction(function () use ($channelId): void {
            // Locks every channel, so two deletes can't each leave the other as the last one.
            // Postgres allows no FOR UPDATE on an aggregate, so it locks the rows and counts them here.
            $count = count(SalesChannel::query()->lockForUpdate()->pluck('id')->all());
            $channel = SalesChannel::query()->findOrFail($channelId->value);

            if ($count === 1) {
                throw new InvalidSalesChannel("The sales channel [{$channel->key}] is the last one, so it cannot be deleted.");
            }

            // Before the delete, so listeners still see the channel and can refuse before a foreign key does.
            SalesChannelDeleted::dispatch($channelId, $channel->key, $channel->type);

            $channel->delete();
        });

        $this->channels->forget();
    }
}
