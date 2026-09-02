<?php

namespace App\Support;

use App\Enums\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use InvalidArgumentException;

class StorefrontLocale
{
    private const COOKIE = 'storefront_locale';

    public static function current(): string
    {
        return App::currentLocale();
    }

    public static function set(Locale|string $locale, bool $remember = false): string
    {
        $locale = self::resolve($locale);

        App::setLocale($locale->value);

        if ($remember) {
            Cookie::queue(Cookie::forever(self::cookieName(), $locale->value));
        }

        return $locale->value;
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $locales = [];

        foreach (Locale::cases() as $locale) {
            $locales[$locale->value] = $locale->label();
        }

        return $locales;
    }

    /**
     * @return array<int, string>
     */
    public static function enabled(): array
    {
        return array_values(array_map(
            fn (Locale $locale): string => $locale->value,
            Locale::enabled(),
        ));
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function enabledOptions(): array
    {
        return array_values(array_map(
            fn (Locale $locale): array => [
                'value' => $locale->value,
                'label' => $locale->label(),
            ],
            Locale::enabled(),
        ));
    }

    public static function cookieName(): string
    {
        return self::COOKIE;
    }

    public static function setFromRequest(Request $request): string
    {
        $cookieLocale = $request->cookie(self::cookieName());

        if (is_string($cookieLocale)) {
            return self::set(self::preferred([$cookieLocale]));
        }

        return self::set(self::preferred($request->getLanguages()));
    }

    /**
     * @param  array<int, string>  $accepted
     */
    public static function preferred(array $accepted): string
    {
        foreach ($accepted as $locale) {
            try {
                return self::resolve($locale)->value;
            } catch (InvalidArgumentException) {
                try {
                    return self::resolve(self::baseLocale(self::normalize($locale)))->value;
                } catch (InvalidArgumentException) {
                    continue;
                }
            }
        }

        return self::fallback()->value;
    }

    public static function normalize(string $locale): string
    {
        $locale = str_replace('-', '_', trim($locale));

        if (preg_match('/^([a-z]{2,3})(?:_([a-zA-Z]{2}|[0-9]{3}))?$/D', $locale, $matches) !== 1) {
            throw new InvalidArgumentException("Invalid locale [{$locale}].");
        }

        $language = strtolower($matches[1]);

        if (! isset($matches[2])) {
            return $language;
        }

        $territory = is_numeric($matches[2]) ? $matches[2] : strtoupper($matches[2]);

        return "{$language}_{$territory}";
    }

    private static function resolve(Locale|string $locale): Locale
    {
        if ($locale instanceof Locale) {
            if (! in_array($locale, Locale::enabled(), true)) {
                throw new InvalidArgumentException("Locale [{$locale->value}] is not enabled.");
            }

            return $locale;
        }

        $locale = self::normalize($locale);

        $value = $locale;
        $locale = Locale::tryFrom($value);

        if ($locale === null || ! in_array($locale, Locale::enabled(), true)) {
            throw new InvalidArgumentException("Locale [{$value}] is not enabled.");
        }

        return $locale;
    }

    private static function fallback(): Locale
    {
        $fallback = self::normalize((string) config('app.fallback_locale', config('app.locale', Locale::English->value)));

        $locale = Locale::tryFrom($fallback);

        if ($locale !== null && in_array($locale, Locale::enabled(), true)) {
            return $locale;
        }

        $enabled = Locale::enabled();

        if ($enabled === []) {
            throw new InvalidArgumentException('At least one storefront locale must be enabled.');
        }

        return $enabled[0];
    }

    private static function baseLocale(string $locale): string
    {
        return explode('_', $locale, 2)[0];
    }
}
