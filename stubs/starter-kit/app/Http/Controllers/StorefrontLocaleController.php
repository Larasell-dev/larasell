<?php

namespace App\Http\Controllers;

use App\Enums\Locale;
use App\Support\StorefrontLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StorefrontLocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(Locale::enabledValues())],
        ]);

        StorefrontLocale::set($validated['locale'], remember: true);

        return back();
    }
}
