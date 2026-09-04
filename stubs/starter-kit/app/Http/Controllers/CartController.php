<?php

namespace App\Http\Controllers;

use App\Support\SessionCart;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Models\CartItem;
use Larasell\Larasell\Price;

class CartController extends Controller
{
    public function __invoke(SessionCart $sessionCart): Response
    {
        $cart = $sessionCart->get();
        $locale = App::currentLocale();
        $items = $cart->purchasableItems();
        $subtotal = $cart->subtotal();
        $total = $cart->total();

        return Inertia::render('Cart/Show', [
            'cart' => [
                'items' => $items->map(fn (CartItem $item): array => [
                    'id' => $item->getKey(),
                    'name' => $item->product->name->get(),
                    'quantity' => $item->quantity,
                    'unitPrice' => Price::format($item->unitPrice(), $cart->currency, $locale),
                    'total' => Price::format($item->total(), $cart->currency, $locale),
                ])->all(),
                'quantity' => $cart->quantity(),
                'subtotal' => $subtotal === null ? null : Price::format($subtotal, $cart->currency, $locale),
                'total' => $total === null ? null : Price::format($total, $cart->currency, $locale),
            ],
        ]);
    }
}
