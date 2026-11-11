<?php

namespace Larasell\Larasell\Seo\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\SalesChannels\SalesChannelId;

/**
 * An old URL became its page's current one again, by RestoreOldUrl. Dispatched inside its
 * transaction: a sync listener writes in it and can undo it by throwing.
 *
 * @public
 */
final readonly class SeoUrlRestored
{
    use Dispatchable;

    public function __construct(
        public SalesChannelId $salesChannelId,
        public string $locale,
        public string $path,
        /** The resource type key of its page, e.g. "product" */
        public string $resourceType,
        public int $resourceId,
        /** Who restored it, and how it came in. */
        public Origin $origin,
    ) {}
}
