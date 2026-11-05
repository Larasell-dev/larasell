<?php

namespace Larasell\Larasell\Storefront\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** An entry point was deleted. Dispatched inside the action's transaction. */
final readonly class EntryPointDeleted
{
    use Dispatchable;

    public function __construct(public int $entryPointId, public int $channelId) {}
}
