<?php

namespace Larasell\Larasell\Seo\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/** A deleted resource's URLs became tombstones. */
class SeoResourceGone
{
    use Dispatchable;

    public function __construct(public readonly Model $resource) {}
}
