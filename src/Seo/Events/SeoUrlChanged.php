<?php

namespace Larasell\Larasell\Seo\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Seo\SeoContext;

/**
 * A page's canonical path changed in one context. A null path means it has no page there.
 * Dispatched once committed.
 */
final readonly class SeoUrlChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        /** The page's resource type key, e.g. "product" */
        public string $resourceType,
        public int $resourceId,
        public SeoContext $context,
        public ?string $oldPath,
        public ?string $newPath,
    ) {}
}
