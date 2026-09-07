<?php

namespace App\Http\Controllers;

use App\Support\SessionCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Larasell\Larasell\Exceptions\Cart\UnavailableShippingOptionException;

class SelectShippingOptionController extends Controller
{
    public function __invoke(Request $request, SessionCart $sessionCart): RedirectResponse
    {
        $data = $request->validate([
            'shipping_option' => ['required', 'string'],
        ]);

        $cart = $sessionCart->existing() ?? abort(404);

        try {
            $cart->selectShippingOption($data['shipping_option']);
        } catch (UnavailableShippingOptionException $exception) {
            throw ValidationException::withMessages([
                'shipping_option' => $exception->getMessage(),
            ]);
        }

        return back();
    }
}
