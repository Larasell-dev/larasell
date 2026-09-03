<?php

namespace App\Http\Controllers;

use App\Support\SessionCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Larasell\Larasell\Exceptions\Cart\CartException;
use Larasell\Larasell\Models\CartItem;

class UpdateCartItemController extends Controller
{
    public function __invoke(Request $request, CartItem $cartItem): RedirectResponse
    {
        $cart = SessionCart::get();

        // TODO: Ideally we just get the cart and check remove it via the cart
        // The cart will then check if the item is in there or not
        abort_unless($cart !== null && $cartItem->cart_id === $cart->getKey(), 404);

        $data = $request->validate([
            'quantity' => ['required', 'integer'],
        ]);

        try {
            $cart->set($cartItem->variant, $data['quantity'], $cartItem->metadata->all());
        } catch (CartException $exception) {
            return back()->with('message', $exception->getMessage());
        }

        return back();
    }
}
