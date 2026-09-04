<?php

namespace App\Http\Controllers;

use App\Support\SessionCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Larasell\Larasell\Exceptions\Cart\CartException;

class AddProductToCartController extends Controller
{
    public function __invoke(Request $request, SessionCart $sessionCart): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $sessionCart->get()->add($data['product_id'], $data['quantity']);
        } catch (CartException $exception) {
            throw ValidationException::withMessages([
                'quantity' => $exception->getMessage(),
            ]);
        }

        return back();
    }
}
