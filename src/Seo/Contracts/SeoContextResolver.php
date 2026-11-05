<?php

namespace Larasell\Larasell\Seo\Contracts;

use Illuminate\Http\Request;
use Larasell\Larasell\Seo\SeoContext;

interface SeoContextResolver
{
    /** The context a storefront request belongs to, or null when it reaches no configured entry point. */
    public function resolve(Request $request): ?SeoContext;
}
