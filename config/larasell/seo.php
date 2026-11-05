<?php

use Larasell\Larasell\Seo\Catalog\CategoryResourceType;
use Larasell\Larasell\Seo\Catalog\ProductResourceType;
use Larasell\Larasell\Seo\DefaultSlugifier;
use Larasell\Larasell\Seo\EntryPointResolver;
use Larasell\Larasell\Seo\Models\SeoUrl;
use Larasell\Larasell\Seo\SuffixCollisionPolicy;

return [
    /*
     * Works out the sales channel and locale of a storefront request. Replace it for setups the
     * entry points below cannot express, such as a headless front end.
     */
    'context_resolver' => EntryPointResolver::class,

    /*
     * The sales channels, keyed by a stable channel key. Each channel is reached through entry
     * points: a base URL (host plus an optional path prefix) that serves one locale.
     *
     * By default a channel serves every supported locale on its URL (app.url when null). A channel
     * with one locale has no prefix; with several, every locale gets one ("/de", "/en-gb").
     *
     * For other layouts, list the entry points yourself. The longest matching prefix wins:
     *
     *     'entry_points' => [
     *         ['url' => 'https://shop.com', 'locale' => 'de'],      // no prefix for the default locale
     *         ['url' => 'https://shop.com/en', 'locale' => 'en'],
     *         ['url' => 'https://shop.co.uk', 'locale' => 'en-GB'], // a domain per locale
     *         ['url' => 'https://shop.ch/fr', 'locale' => 'fr-CH'], // a domain and a prefix
     *     ],
     */
    'channels' => [
        'default' => [
            'url' => null,
            'locales' => null, // null serves larasell.locale.supported
        ],
    ],

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

    'models' => [
        'seo_url' => SeoUrl::class,
    ],
];
