<?php

namespace Larasell\Larasell\SalesChannels\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\SalesChannels\SalesChannelId;

/**
 * A sales channel is being deleted. Dispatched inside the action's transaction, right before the
 * row goes: a sync listener can refuse by throwing, which undoes the delete; implement
 * ShouldHandleEventsAfterCommit or ShouldQueueAfterCommit to run once it is gone.
 */
final readonly class SalesChannelDeleted
{
    use Dispatchable;

    public function __construct(public SalesChannelId $channelId, public string $key, public string $type, public Origin $origin) {}
}
