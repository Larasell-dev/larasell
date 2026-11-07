<?php

namespace Larasell\Larasell\SalesChannels\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\SalesChannels\SalesChannelId;
use RuntimeException;

/**
 * Another module still points at the channel, so it cannot be deleted. Thrown by listeners of
 * SalesChannelDeleted inside the delete's transaction, which undoes the delete. Deactivate the
 * channel instead.
 */
final class SalesChannelInUse extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    public static function carts(SalesChannelId $channelId, int $count): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.in-use-by-carts',
            message: "Sales channel [{$channelId}] cannot be deleted while {$count} carts were made in it; deactivate it instead.",
            why: 'Carts keep pointing at the channel they were made in.',
            fix: 'Deactivate the channel instead of deleting it.',
        );
    }

    public static function orders(SalesChannelId $channelId, int $count): self
    {
        return self::structured(
            errorCode: 'larasell.sales-channel.in-use-by-orders',
            message: "Sales channel [{$channelId}] cannot be deleted while {$count} orders were made in it; deactivate it instead.",
            why: 'Orders keep pointing at the channel they were made in.',
            fix: 'Deactivate the channel instead of deleting it.',
        );
    }
}
