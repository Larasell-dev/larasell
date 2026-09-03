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
            'navigation' => Inertia::once(fn (): array => app(NavigationProp::class)->prop()),
        ];
    }
}
