<?php

namespace Larasell\Larasell\Seo;

use Illuminate\Database\Eloquent\Model;
use Larasell\Larasell\Seo\Contracts\GoneStrategy;
use Larasell\Larasell\Seo\Models\SeoUrl;

/** A permanent redirect to the replacement chosen when the page was deleted, while it is available. */
final class RedirectToReplacement implements GoneStrategy
{
    public function __construct(private readonly SeoPages $pages) {}

    public function redirect(SeoUrl $url, SeoContext $context): ?SeoRedirect
    {
        $replacement = $url->replacement;
        $location = $replacement instanceof Model ? $this->pages->availableUrl($replacement, $context) : null;

        return $location === null ? null : new SeoRedirect($location, 301);
    }
}
