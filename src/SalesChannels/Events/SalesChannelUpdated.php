<?php

namespace Larasell\Larasell\SalesChannels\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\SalesChannels\SalesChannelId;

/**
 * A sales channel changed. $changed names exactly the fields whose value differs from before, out of
 * "name", "active", "default_locale", "default_currency", "locales" and "currencies"; a rename is an
 * update whose $changed has "name". Dispatched inside the action's transaction, and not at all when
 * nothing changed.
 */
final readonly class SalesChannelUpdated
{
    use Dispatchable;

    /** @param  list<string>  $changed */
    public function __construct(public SalesChannelId $channelId, public array $changed) {}
}
