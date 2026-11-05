<?php

namespace Larasell\Larasell\Seo;

use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Seo\Contracts\HiddenPagePolicy;

/** Hidden pages answer 404 and keep their URLs, so publishing them again restores the same URL. */
final class NotFoundForHiddenPages implements HiddenPagePolicy
{
    public function redirect(Model $resource, SeoContext $context): ?SeoRedirect
    {
        return null;
    }
}
