<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SetStorefrontLocale
{
    public const COOKIE_NAME = 'storefront_locale';

    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolve($request));
        Inertia::share([
            'locales' => fn (): array => array_map(
                fn (Locale $locale): string => $locale->value,
                Locale::enabled(),
            ),
        ]);

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $fallback = (string) config('app.fallback_locale', 'en');
        $locales = array_map(
            fn (Locale $locale): string => $locale->value,
            Locale::enabled(),
        );

        if ($locales === []) {
            return $fallback;
        }

        $cookie = $request->cookie(self::COOKIE_NAME);

        if (is_string($cookie) && in_array($cookie, $locales, true)) {
            return $cookie;
        }

        return $request->getPreferredLanguage([
            $fallback,
            ...array_values(array_diff($locales, [$fallback])),
        ]) ?? $fallback;
    }
}
