<?php

namespace Larasell\Larasell\Seo\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Seo\Models\SeoUrl;

/** An old or gone URL was deleted: an admin removed it, or another page claimed its path. */
class SeoUrlReleased implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly SeoUrl $url) {}
}
