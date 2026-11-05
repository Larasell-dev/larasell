<?php

namespace Larasell\Larasell\Seo\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A deleted page's URLs became tombstones. Dispatched once committed. */
final readonly class SeoResourceGone implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        /** The page's resource type key, e.g. "product" */
        public string $resourceType,
        public int $resourceId,
    ) {}
}
