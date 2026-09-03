<?php

namespace App\Http\Middleware;

use App\Inertia\NavigationProp;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'locale' => fn (): string => app()->getLocale(),
            'fallbackLocale' => fn (): string => (string) config('app.fallback_locale', 'en'),
            'navigation' => Inertia::once(fn (): array => app(NavigationProp::class)->prop()),
        ];
    }
}
