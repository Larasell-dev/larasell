<?php

namespace App\Http\Middleware;

use App\Inertia\CartProp;
use App\Inertia\NavigationProp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'locale' => fn (): string => App::currentLocale(),
            'fallbackLocale' => fn (): string => (string) config('app.fallback_locale', 'en'),
            'cart' => Inertia::once(fn (): array => app(CartProp::class)->prop()),
            'flash' => [
                'message' => fn (): ?string => $request->session()->get('message'),
            ],
            'navigation' => Inertia::once(fn (): array => app(NavigationProp::class)->prop()),
        ];
    }
}
