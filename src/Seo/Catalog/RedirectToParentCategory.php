<?php

namespace Larasell\Larasell\Seo\Catalog;

use Larasell\Larasell\Catalog\Models\Category;
use Larasell\Larasell\Seo\Contracts\GoneStrategy;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Seo\SeoPages;
use Larasell\Larasell\Seo\SeoRedirect;
use Larasell\Larasell\Storefront\StorefrontContext;

/**
 * A temporary redirect from a deleted category to its parent, while the parent is available.
 * Temporary, because the target follows the parent's current URL and may change.
 */
final class RedirectToParentCategory implements GoneStrategy
{
    public function __construct(private readonly SeoPages $pages) {}

    public function redirect(SeoUrl $url, StorefrontContext $context): ?SeoRedirect
    {
        $parentId = $url->gone_snapshot['parent_id'] ?? null;
        $parent = ! is_int($parentId) ? null : Category::query()->find($parentId);
        $location = $parent === null ? null : $this->pages->availableUrl($parent, $context);

        return $location === null ? null : new SeoRedirect($location, 302);
    }
}
