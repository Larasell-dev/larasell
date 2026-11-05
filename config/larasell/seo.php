<?php

use Larasell\Larasell\Seo\EntryPointResolver;

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
];
