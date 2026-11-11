<?php

namespace Larasell\Larasell\Storefront\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\SalesChannelId;
use Larasell\Larasell\Storefront\EntryPointId;

/** A storefront channel can be reached through a new entry point, maybe in a locale it had none in. Dispatched inside the action's transaction. */
final readonly class EntryPointCreated
{
    use Dispatchable;

    public function __construct(
        public EntryPointId $entryPointId,
        public SalesChannelId $channelId,
        public Locale $locale,
        /** The currency a visit starts in, or null for the channel's default. */
        public ?Currency $defaultCurrency,
        /** Who made the change and how it came in. */
        public Origin $origin,
    ) {}
}
