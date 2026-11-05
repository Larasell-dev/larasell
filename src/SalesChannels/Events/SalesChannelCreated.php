<?php

namespace Larasell\Larasell\SalesChannels\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** A sales channel was created. Dispatched inside the action's transaction. */
final readonly class SalesChannelCreated
{
    use Dispatchable;

    public function __construct(public int $channelId, public string $key, public string $type) {}
}
