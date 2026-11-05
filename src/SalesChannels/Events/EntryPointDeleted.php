<?php

namespace Larasell\Larasell\SalesChannels\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** An entry point was deleted. Records that stored it fall back to their channel's primary one. Dispatched inside the action's transaction. */
final readonly class EntryPointDeleted
{
    use Dispatchable;

    public function __construct(public int $entryPointId, public int $channelId) {}
}
