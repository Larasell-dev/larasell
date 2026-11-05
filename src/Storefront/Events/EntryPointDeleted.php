<?php

namespace Larasell\Larasell\Storefront\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\SalesChannels\SalesChannelId;
use Larasell\Larasell\Storefront\EntryPointId;

/** An entry point was deleted. Dispatched inside the action's transaction. */
final readonly class EntryPointDeleted
{
    use Dispatchable;

    public function __construct(public EntryPointId $entryPointId, public SalesChannelId $channelId) {}
}
