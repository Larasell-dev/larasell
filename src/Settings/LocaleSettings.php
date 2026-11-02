<?php

namespace Larasell\Larasell\Settings;

use InvalidArgumentException;
use Larasell\Larasell\Enums\Locale;

final class LocaleSettings
{
    /** @return non-empty-list<Locale> */
    public function supported(): array
    {
        $supported = array_values(array_unique(array_map(
            fn (mixed $tag): Locale => self::locale($tag, 'larasell.locale.supported'),
            (array) config('larasell.locale.supported', []),
        ), SORT_REGULAR));

        if ($supported === []) {
            throw new InvalidArgumentException('Configure at least one locale in larasell.locale.supported.');
        }

        return $supported;
    }

    public function default(): Locale
    {
        $default = self::locale(config('larasell.locale.default'), 'larasell.locale.default');

        if (! in_array($default, $this->supported(), true)) {
            throw new InvalidArgumentException("The default locale [{$default->value}] must be one of larasell.locale.supported.");
        }

        return $default;
    }

    /**
     * Picks the supported locale that best matches an Accept-Language header, or the default locale.
     * Each language range, in order of its quality, matches itself or a shorter prefix ("de-CH" matches "de"),
     * then the first supported locale it is a prefix of ("de" matches "de-DE").
     */
    public function negotiate(?string $acceptLanguage): Locale
    {
        $supported = array_map(fn (Locale $locale): string => $locale->value, $this->supported());

        foreach (self::languageRanges((string) $acceptLanguage) as $range) {
            if ($range === '*') {
                break;
            }

            foreach (Locale::lookupChain($range) as $candidate) {
                if (in_array($candidate, $supported, true)) {
                    return Locale::from($candidate);
                }
            }

            foreach ($supported as $locale) {
                if (str_starts_with($locale, $range.'-')) {
                    return Locale::from($locale);
                }
            }
        }

        return $this->default();
    }

    /**
     * The canonical language ranges of an Accept-Language header, highest quality first.
     * Ranges with a quality of 0 and ranges that are not valid locales are left out.
     *
     * @return list<string>
     */
    private static function languageRanges(string $header): array
    {
        $ranges = [];

        foreach (explode(',', $header) as $entry) {
            $parameters = array_map('trim', explode(';', $entry));
            $range = array_shift($parameters);
            $quality = 1.0;

            foreach ($parameters as $parameter) {
                if (preg_match('/^q=([01](?:\.\d{0,3})?)$/i', $parameter, $matches) === 1) {
                    $quality = (float) $matches[1];
                }
            }

            if ($quality <= 0 || $range === '') {
                continue;
            }

            try {
                $range = $range === '*' ? '*' : Locale::canonicalize($range);
            } catch (InvalidArgumentException) {
                continue;
            }

            $ranges[] = ['range' => $range, 'quality' => $quality];
        }

        // usort is stable, so ranges of equal quality keep the order the client sent them in.
        usort($ranges, fn (array $a, array $b): int => $b['quality'] <=> $a['quality']);

        return array_column($ranges, 'range');
    }

    private static function locale(mixed $tag, string $key): Locale
    {
        $locale = match (true) {
            $tag instanceof Locale => $tag,
            is_string($tag) => Locale::tryFrom(Locale::canonicalize($tag)),
            default => null,
        };

        if ($locale === null) {
            throw new InvalidArgumentException("[{$key}] contains an unknown locale.");
        }

        return $locale;
    }
}
