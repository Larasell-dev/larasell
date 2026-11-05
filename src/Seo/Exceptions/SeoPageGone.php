<?php

namespace Larasell\Larasell\Seo\Exceptions;

use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The page was deleted and no gone strategy redirects its URL (410). The snapshot holds what its
 * resource type kept about it, e.g. a product's name and category IDs, for a helpful error page.
 */
class SeoPageGone extends HttpException
{
    /** @var array<string, mixed> */
    public readonly array $snapshot;

    public function __construct(public readonly SeoUrl $url, public readonly SalesContext $context)
    {
        $this->snapshot = $url->gone_snapshot ?? [];

        parent::__construct(410, 'The page was removed.');
    }
}
