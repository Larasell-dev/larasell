<?php

namespace Larasell\Larasell\Workflows;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\Seo\SeoUrlManager;

/**
 * The custom URLs a workflow writes after the catalog action, keyed by locale: a path makes it the
 * page's custom URL in every channel serving the locale, null goes back to the automatic URL.
 *
 * @internal
 */
final readonly class CustomUrls
{
    public function __construct(private SeoUrlManager $urls) {}

    /**
     * The contexts each locale's URL is written in. Checked before the workflow writes anything.
     *
     * @param  array<string, string|null>  $paths
     * @return list<array{SalesContext, string|null}>
     */
    public function plan(array $paths): array
    {
        $plan = [];

        foreach ($paths as $locale => $path) {
            $locale = Locale::canonicalize((string) $locale);
            $contexts = array_filter($this->urls->contexts(), fn (SalesContext $context): bool => $context->locale->value === $locale);

            if ($contexts === []) {
                throw new InvalidArgumentException("No sales channel serves [{$locale}], so it has no URLs.");
            }

            foreach ($contexts as $context) {
                $plan[] = [$context, $path];
            }
        }

        return $plan;
    }

    /** @param  list<array{SalesContext, string|null}>  $plan */
    public function apply(Model $page, array $plan): void
    {
        foreach ($plan as [$context, $path]) {
            $path === null
                ? $this->urls->resetToAutomatic($page, $context)
                : $this->urls->setCustom($page, $context, $path);
        }
    }
}
