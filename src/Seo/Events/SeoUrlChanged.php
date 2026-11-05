<?php

namespace Larasell\Larasell\Seo\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Seo\SeoContext;

/** A resource's canonical path changed in one context. A null path means it has no page there. */
class SeoUrlChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Model $resource,
        public readonly SeoContext $context,
        public readonly ?string $oldPath,
        public readonly ?string $newPath,
    ) {}
}
