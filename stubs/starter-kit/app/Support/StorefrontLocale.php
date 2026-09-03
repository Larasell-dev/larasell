<?php

namespace App\Support;

use App\Enums\Locale;
use Illuminate\Http\Request;

class StorefrontLocale
{
    public const COOKIE_NAME = 'storefront_locale';

    public static function current(): string
    {
        $fallback = (string) config('app.fallback_locale', 'en');
        $locales = array_map(
            fn (Locale $locale): string => $locale->value,
            Locale::enabled(),
        );

        if ($locales === []) {
            return $fallback;
        }

        /** @var Request $request */
        $request = app('request');
        $cookie = $request->cookie(self::COOKIE_NAME);

        if (is_string($cookie) && in_array($cookie, $locales, true)) {
            return $cookie;
        }

        $negotiatedLocales = [
            $fallback,
            ...array_values(array_diff($locales, [$fallback])),
        ];

        return $request->getPreferredLanguage($negotiatedLocales) ?? $fallback;
    }
}
