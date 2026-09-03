<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use App\Support\StorefrontLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SetStorefrontLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(StorefrontLocale::current());
        Inertia::share([
            'locales' => fn (): array => array_map(
                fn (Locale $locale): string => $locale->value,
                Locale::enabled(),
            ),
        ]);

        return $next($request);
    }
}
