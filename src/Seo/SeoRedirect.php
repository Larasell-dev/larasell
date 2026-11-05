<?php

namespace Larasell\Larasell\Seo;

final readonly class SeoRedirect
{
    public function __construct(
        /** The absolute URL to redirect to. */
        public string $url,
        public int $status = 301,
    ) {}
}
