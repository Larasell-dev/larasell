<?php

namespace Larasell\Larasell\SalesChannels\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Locale;

/** A channel can be reached through a new entry point, maybe in a locale it had none in. Dispatched inside the action's transaction. */
final readonly class EntryPointCreated
{
    use Dispatchable;

    public function __construct(
        public int $entryPointId,
        public int $channelId,
        public Locale $locale,
        public Currency $currency,
    ) {}
}
