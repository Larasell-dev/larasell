<?php

namespace Larasell\Larasell\Seo\Contracts;

interface PathNormalizer
{
    /**
     * The one form a decoded path below the entry point is stored and looked up in, without
     * leading or trailing slashes. Requests for any other form are redirected to it.
     */
    public function normalize(string $path): string;
}
