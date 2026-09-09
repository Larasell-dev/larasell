<?php

namespace App\Http\Middleware;

use App\Inertia\CartProp;
use App\Inertia\NavigationProp;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    'email' => $user->email,
                    'id' => $user->getKey(),
                    'name' => $user->name,
                ],
            ],
            'cart' => fn (): array => app(CartProp::class)->prop(),
            'navigation' => Inertia::once(fn (): array => app(NavigationProp::class)->prop()),
        ];
    }
}
