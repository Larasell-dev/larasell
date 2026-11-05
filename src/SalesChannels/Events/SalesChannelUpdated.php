<?php

namespace Larasell\Larasell\SalesChannels\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\SalesChannels\SalesChannelId;

/** A sales channel changed; $changed names what, e.g. "name", "locales", "currencies". Dispatched inside the action's transaction. */
final readonly class SalesChannelUpdated
{
    use Dispatchable;

    /** @param  list<string>  $changed */
    public function __construct(public SalesChannelId $channelId, public array $changed) {}
}
