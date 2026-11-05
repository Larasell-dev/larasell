<?php

namespace Larasell\Larasell\SalesChannels\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\SalesChannels\SalesChannelId;

/** A sales channel was created. Dispatched inside the action's transaction. */
final readonly class SalesChannelCreated
{
    use Dispatchable;

    public function __construct(public SalesChannelId $channelId, public string $key, public string $type) {}
}
