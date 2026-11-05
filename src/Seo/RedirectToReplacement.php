<?php

namespace Larasell\Larasell\Seo;

use Larasell\Larasell\Seo\Contracts\GoneStrategy;
use Larasell\Larasell\Seo\Models\SeoUrl;

/** A permanent redirect to the replacement chosen when the page was deleted, while it is available. */
final class RedirectToReplacement implements GoneStrategy
{
    public function __construct(
        private readonly SeoPages $pages,
        private readonly SeoResources $resources,
    ) {}

    public function redirect(SeoUrl $url, SeoContext $context): ?SeoRedirect
    {
        $replacement = $this->resources->page($url->replacement_type, $url->replacement_id);
        $location = $replacement === null ? null : $this->pages->availableUrl($replacement, $context);

        return $location === null ? null : new SeoRedirect($location, 301);
    }
}
