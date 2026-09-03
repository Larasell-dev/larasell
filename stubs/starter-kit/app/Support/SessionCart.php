<?php

namespace App\Support;

use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Settings\CurrencySettings;

class SessionCart
{
    public static function get(): ?Cart
    {
        $cartId = session('cart_id');

        if ($cartId === null) {
            return null;
        }

        $cart = Cart::query()->find($cartId);

        return $cart instanceof Cart ? $cart : null;
    }

    public static function current(CurrencySettings $currencies): Cart
    {
        $cart = self::get();

        if ($cart instanceof Cart) {
            return $cart;
        }

        $cart = Cart::query()->create([
            'currency' => $currencies->enabled()[0],
            'session_id' => session()->getId(),
        ]);

        session(['cart_id' => $cart->getKey()]);

        return $cart;
    }
}
