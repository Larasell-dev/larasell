<?php

namespace Larasell\Larasell\Seo\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Seo\SeoContext;

/** A resource's canonical path changed in one context. A null path means it has no page there. Dispatched once committed. */
class SeoUrlChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Model $resource,
        public readonly SeoContext $context,
        public readonly ?string $oldPath,
        public readonly ?string $newPath,
    ) {}
}
