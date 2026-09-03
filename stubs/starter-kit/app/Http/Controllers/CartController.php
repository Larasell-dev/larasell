<?php

namespace App\Http\Controllers;

use App\Support\SessionCart;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\CartItem;
use Larasell\Larasell\Price;

class CartController extends Controller
{
    public function show(): Response
    {
        $cart = SessionCart::get()?->load(['items.product', 'items.variant.product']);

        return Inertia::render('Cart/Show', [
            'cart' => $cart === null ? null : $this->cartPayload($cart),
        ]);
    }

    /** @return array<string, mixed> */
    private function cartPayload(Cart $cart): array
    {
        $locale = app()->getLocale();
        $shippingOption = $cart->shippingOption();

        return [
            'id' => $cart->getKey(),
            'currency' => $cart->currency->value,
            'quantity' => $cart->quantity(),
            'items' => $cart->items
                ->map(fn (CartItem $item): array => [
                    'id' => $item->getKey(),
                    'productName' => $item->product->name->get(),
                    'quantity' => $item->quantity,
                    'minQuantity' => $item->variant->minimumQuantity(),
                    'maxQuantity' => $item->variant->maximumQuantity(),
                    'unitPrice' => Price::format($item->unitPrice(), $cart->currency, $locale),
                    'total' => Price::format($item->total(), $cart->currency, $locale),
                    'sku' => $item->sku(),
                    'updateUrl' => route('cart.items.update', $item->getKey()),
                    'removeUrl' => route('cart.items.destroy', $item->getKey()),
                ])
                ->all(),
            'subtotal' => $cart->subtotal() === null ? null : Price::format($cart->subtotal(), $cart->currency, $locale),
            'shipping' => $shippingOption === null ? null : [
                'name' => $shippingOption->name,
                'price' => Price::format($shippingOption->price, $cart->currency, $locale),
            ],
            'discounts' => $cart->discounts()
                ->map(fn ($discount): array => [
                    'identifier' => $discount->identifier,
                    'name' => $discount->name,
                    'code' => $discount->code,
                    'total' => Price::format($discount->total(), $cart->currency, $locale),
                ])
                ->all(),
            'discountTotal' => Price::format($cart->discountTotal(), $cart->currency, $locale),
            'total' => $cart->total() === null ? null : Price::format($cart->total(), $cart->currency, $locale),
            'promotionCodes' => $cart->promotionCodes(),
        ];
    }
}
