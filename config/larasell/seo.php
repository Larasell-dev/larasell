<?php

use Larasell\Larasell\Seo\Catalog\CategoryResourceType;
use Larasell\Larasell\Seo\Catalog\ProductResourceType;
use Larasell\Larasell\Seo\Catalog\RedirectToParentCategory;
use Larasell\Larasell\Seo\DefaultPathNormalizer;
use Larasell\Larasell\Seo\DefaultSlugifier;
use Larasell\Larasell\Seo\NotFoundForHiddenPages;
use Larasell\Larasell\Seo\RedirectToReplacement;
use Larasell\Larasell\Seo\SuffixCollisionPolicy;

return [
    /*
     * The kinds of models that have pages. Each type builds the automatic path of its models and may
     * reserve path prefixes for itself, e.g. products reserve "p".
     */
    'resources' => [
        ProductResourceType::class,
        CategoryResourceType::class,
    ],

    /*
     * Paths no page may use, together with everything below them, e.g. "cart" or "account".
     */
    'reserved_paths' => [],

    /* Turns a name into a path segment. */
    'slugifier' => DefaultSlugifier::class,

    /* What happens when a path is taken: automatic paths get "-2", custom paths fail. */
    'collision_policy' => SuffixCollisionPolicy::class,

    /* The one form of a path; requests for any other form are redirected to it. */
    'path_normalizer' => DefaultPathNormalizer::class,

    /*
     * Where URLs of deleted pages lead, per resource type. The first strategy that returns a redirect
     * wins; when none does, the request fails with SeoPageGone (410). Render that exception to show
     * your own page, e.g. in bootstrap/app.php with $exceptions->render(fn (SeoPageGone $e) => ...).
     */
    'gone' => [
        ProductResourceType::class => [RedirectToReplacement::class],
        CategoryResourceType::class => [RedirectToReplacement::class, RedirectToParentCategory::class],
    ],

    /*
     * Where URLs of existing but unavailable pages lead, e.g. unpublished products. The default fails
     * the request with SeoPageHidden (404) and keeps the URLs for when the page is published again.
     */
    'hidden_policy' => NotFoundForHiddenPages::class,
];
