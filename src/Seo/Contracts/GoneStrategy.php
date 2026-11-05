<?php

namespace Larasell\Larasell\Seo\Contracts;

use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Seo\SeoRedirect;
use Larasell\Larasell\Storefront\StorefrontContext;

/**
 * Decides where a deleted page's URL leads, at request time, so the target is always current.
 * Strategies run in the order configured in larasell.seo.gone; the first redirect wins, and
 * when none redirects the request fails with SeoPageGone (410).
 */
interface GoneStrategy
{
    public function redirect(SeoUrl $url, StorefrontContext $context): ?SeoRedirect;
}
