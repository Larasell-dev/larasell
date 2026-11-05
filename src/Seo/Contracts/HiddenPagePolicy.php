<?php

namespace Larasell\Larasell\Seo\Contracts;

use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\Seo\SeoRedirect;

/**
 * Decides where a URL of an existing but unavailable page leads, e.g. an unpublished product.
 * Null fails the request with SeoPageHidden (404).
 */
interface HiddenPagePolicy
{
    public function redirect(Model $resource, SalesContext $context): ?SeoRedirect;
}
