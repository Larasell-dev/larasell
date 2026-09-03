<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Support\StorefrontLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StorefrontLocaleController
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::enum(Locale::class)->only(Locale::enabled())],
        ]);

        return back()->withCookie(cookie()->forever(
            StorefrontLocale::COOKIE_NAME,
            $validated['locale'],
        ));
    }
}
