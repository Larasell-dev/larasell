<?php

namespace App\Inertia;

use Illuminate\Support\Facades\App;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\CartItem;
use Larasell\Larasell\Price;

final class CartDetails
{
    /**
     * @return array{
     *     items: array<int, array{id: mixed, name: string, options: array<int, array{name: string, value: string}>, quantity: int, unitPrice: string, total: string, discountTotal: string|null, totalAfterDiscount: string}>,
     *     quantity: int,
     *     subtotal: string|null,
     *     discounts: array<int, array{identifier: string, name: string, code: string|null, total: string}>,
     *     promotionCodes: array<int, array{code: string, name: string|null, total: string|null, applies: bool}>,
     *     total: string|null
     * }
     */
    public function toArray(Cart $cart): array
    {
        $locale = App::currentLocale();
        $discounts = $cart->discounts();
        $subtotal = $cart->subtotal();
        $total = $cart->total();

        return [
            'items' => $cart->purchasableItems()->map(function (CartItem $item) use ($cart, $locale): array {
                $discountTotal = $item->discountTotal();

                return [
                    'id' => $item->getKey(),
                    'name' => $item->product->name->get(),
                    'options' => collect($item->variant->options())
                        ->map(fn (array $option): array => [
                            'name' => $option['attribute_name'],
                            'value' => $option['value_name'],
                        ])
                        ->values()
                        ->all(),
                    'quantity' => $item->quantity,
                    'unitPrice' => Price::format($item->unitPrice(), $cart->currency, $locale),
                    'total' => Price::format($item->total(), $cart->currency, $locale),
                    'discountTotal' => $discountTotal->isPositive()
                        ? Price::format($discountTotal, $cart->currency, $locale)
                        : null,
                    'totalAfterDiscount' => Price::format($item->totalAfterDiscount(), $cart->currency, $locale),
                ];
            })->all(),
            'quantity' => $cart->quantity(),
            'subtotal' => $subtotal === null ? null : Price::format($subtotal, $cart->currency, $locale),
            'discounts' => $discounts->map(fn (DiscountResult $discount): array => [
                'identifier' => $discount->identifier,
                'name' => $discount->name,
                'code' => $discount->code,
                'total' => Price::format($discount->total(), $cart->currency, $locale),
            ])->values()->all(),
            'promotionCodes' => collect($cart->promotionCodes())
                ->map(function (string $code) use ($discounts, $cart, $locale): array {
                    $discount = $discounts->first(
                        fn (DiscountResult $discount): bool => $discount->code === $code,
                    );

                    return [
                        'code' => $code,
                        'name' => $discount?->name,
                        'total' => $discount === null
                            ? null
                            : Price::format($discount->total(), $cart->currency, $locale),
                        'applies' => $discount !== null,
                    ];
                })
                ->values()
                ->all(),
            'total' => $total === null ? null : Price::format($total, $cart->currency, $locale),
        ];
    }
}
