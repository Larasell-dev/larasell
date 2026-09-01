<?php

namespace Larasell\Larasell\Admin\Http\Controllers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait ResolvesAdminUser
{
    protected function adminUser(Request $request): Model
    {
        $admin = $request->user(config('larasell-admin.guard', 'larasell-admin'));

        if (! $admin instanceof Model) {
            throw new AuthenticationException;
        }

        return $admin;
    }
}
