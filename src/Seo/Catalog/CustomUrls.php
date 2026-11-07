<?php

namespace Larasell\Larasell\Seo\Catalog;

use InvalidArgumentException;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\Seo\SeoUrlManager;

/**
 * Custom URLs to save with a product or category: attach it to the catalog action's payload, e.g.
 * `new Attachments(new CustomUrls(['de' => 'winter-angebot']))`. A path becomes the page's custom
 * URL in every channel serving the locale; null goes back to the automatic URL. They are written in
 * the action's transaction, so a taken path undoes the catalog write.
 */
final readonly class CustomUrls
{
    /** @var list<array{SalesContext, string|null}> */
    public array $plan;

    /**
     * @param  array<string, string|null>  $paths  Custom paths by locale
     *
     * @throws InvalidArgumentException when no sales channel serves a locale, before anything is written
     */
    public function __construct(array $paths)
    {
        $contexts = app(SeoUrlManager::class)->contexts();
        $plan = [];

        foreach ($paths as $locale => $path) {
            $locale = Locale::canonicalize((string) $locale);
            $served = array_filter($contexts, fn (SalesContext $context): bool => $context->locale->value === $locale);

            if ($served === []) {
                throw new InvalidArgumentException("No sales channel serves [{$locale}], so it has no URLs.");
            }

            foreach ($served as $context) {
                $plan[] = [$context, $path];
            }
        }

        $this->plan = $plan;
    }
}
