<?php

namespace Larasell\Larasell\Seo\Catalog;

use InvalidArgumentException;
use Larasell\Larasell\Enums\Locale;
use Larasell\Larasell\SalesChannels\SalesContext;
use Larasell\Larasell\Seo\SeoUrlManager;

/**
 * Custom URLs to save with a product or category: attach it to the catalog action's payload, e.g.
 * `new Attachments(CustomUrls::byLocale(['de' => 'winter-angebot']))`. A path becomes the page's
 * custom URL; null goes back to the automatic URL. They are written in the action's transaction,
 * so a rejected path undoes the catalog write with a CustomUrlRejected naming its channel.
 */
final readonly class CustomUrls
{
    /** @param  list<array{SalesContext, string|null}>  $plan */
    private function __construct(public array $plan) {}

    /**
     * The same path in every channel serving each locale.
     *
     * @param  array<string, string|null>  $paths  Custom paths by locale
     *
     * @throws InvalidArgumentException when no channel serves a locale, before anything is written
     */
    public static function byLocale(array $paths): self
    {
        $plan = [];

        foreach ($paths as $locale => $path) {
            $locale = Locale::canonicalize((string) $locale);
            $served = self::contexts(fn (SalesContext $context): bool => $context->locale->value === $locale);

            if ($served === []) {
                throw new InvalidArgumentException("No sales channel serves [{$locale}], so it has no URLs.");
            }

            foreach ($served as $context) {
                $plan[] = [$context, $path];
            }
        }

        return new self($plan);
    }

    /**
     * Each channel's own path in one locale, e.g. as an admin form edits them.
     *
     * @param  array<int, string|null>  $paths  Custom paths by sales channel ID
     *
     * @throws InvalidArgumentException when a channel does not serve the locale, before anything is written
     */
    public static function byChannel(Locale|string $locale, array $paths): self
    {
        $locale = $locale instanceof Locale ? $locale->value : Locale::canonicalize($locale);
        $plan = [];

        foreach ($paths as $channelId => $path) {
            $served = self::contexts(fn (SalesContext $context): bool => $context->locale->value === $locale && $context->channelId->value === $channelId);

            if ($served === []) {
                throw new InvalidArgumentException("Sales channel [{$channelId}] does not serve [{$locale}], so it has no URLs there.");
            }

            $plan[] = [$served[0], $path];
        }

        return new self($plan);
    }

    /**
     * @param  callable(SalesContext): bool  $filter
     * @return list<SalesContext>
     */
    private static function contexts(callable $filter): array
    {
        return array_values(array_filter(app(SeoUrlManager::class)->contexts(), $filter));
    }
}
