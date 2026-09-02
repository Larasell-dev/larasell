<?php

namespace App\Http\Middleware;

use App\Support\StorefrontLocale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function shareOnce(Request $request): array
    {
        StorefrontLocale::setFromRequest($request);

        return [
            'locale' => Inertia::once(fn (): array => [
                'current' => StorefrontLocale::current(),
                'enabled' => StorefrontLocale::enabledOptions(),
            ]),
        ];
    }
}
