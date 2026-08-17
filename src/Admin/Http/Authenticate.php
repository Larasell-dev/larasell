<?php

namespace Larasell\Larasell\Admin\Http;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        return route('larasell.admin.login');
    }
}
