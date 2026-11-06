<?php

namespace Larasell\Larasell\SalesChannels;

use RuntimeException;

/**
 * Another module still points at the channel, so it cannot be deleted. Thrown by listeners of
 * SalesChannelDeleted inside the delete's transaction, which undoes the delete. Deactivate the
 * channel instead.
 */
final class SalesChannelInUse extends RuntimeException
{
    public static function carts(SalesChannelId $channelId, int $count): self
    {
        return new self("Sales channel [{$channelId}] cannot be deleted while {$count} carts were made in it; deactivate it instead.");
    }

    public static function orders(SalesChannelId $channelId, int $count): self
    {
        return new self("Sales channel [{$channelId}] cannot be deleted while {$count} orders were made in it; deactivate it instead.");
    }
}
