<?php

namespace Larasell\Larasell\Seo;

use Larasell\Larasell\Ids\Id;
use Larasell\Larasell\Seo\Models\SeoUrl;

/** The ID of one of the core's SEO URL rows, e.g. an old URL of a product. */
final readonly class SeoUrlId extends Id
{
    protected static function model(): string
    {
        return SeoUrl::class;
    }
}
