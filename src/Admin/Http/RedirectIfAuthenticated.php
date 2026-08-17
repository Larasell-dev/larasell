<?php

namespace Larasell\Larasell\Admin\Http;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated as Middleware;
use Illuminate\Http\Request;

class RedirectIfAuthenticated extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        return route(config('larasell-admin.home', 'larasell.admin.home'));
    }
}
