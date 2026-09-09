<?php

namespace App\Inertia;

use Illuminate\Support\Facades\App;
use Larasell\Larasell\Address;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\OrderItem;
use Larasell\Larasell\Price;

final class OrderDetails
{
    /**
     * @return array{
     *     number: string,
     *     placedAt: string,
     *     publicId: string,
     *     status: string,
     *     total: string
     * }
     */
    public function summary(Order $order): array
    {
        $locale = App::currentLocale();

        return [
            'publicId' => $order->public_id,
            'number' => $order->number,
            'status' => $order->status->value,
            'total' => Price::format($order->total, $order->currency, $locale),
            'placedAt' => $order->created_at->toDateString(),
        ];
    }

    /**
     * @return array{
     *     billingAddress: array<int, string>|null,
     *     customerEmail: string,
     *     customerName: string,
     *     discounts: array<int, array{code: string|null, identifier: string, name: string, total: string}>,
     *     items: array<int, array{id: mixed, name: string, quantity: int, unitPrice: string, total: string, discountTotal: string|null, totalAfterDiscount: string}>,
     *     number: string,
     *     placedAt: string,
     *     publicId: string,
     *     shipping: array{name: string|null, price: string}|null,
     *     shippingAddress: array<int, string>|null,
     *     status: string,
     *     subtotal: string,
     *     tax: string|null,
     *     total: string
     * }
     */
    public function toArray(Order $order): array
    {
        $order->loadMissing('items');

        $locale = App::currentLocale();
        $billingAddress = $this->addressLines($order->billing_address);
        $shippingAddress = $this->addressLines($order->shipping_address);
        $sameAddress = $billingAddress !== null && $billingAddress === $shippingAddress;

        return [
            ...$this->summary($order),
            'customerEmail' => $order->customer_email,
            'customerName' => $order->customer_name,
            'billingAddress' => $sameAddress ? null : $billingAddress,
            'shippingAddress' => $shippingAddress,
            'subtotal' => Price::format($order->subtotal, $order->currency, $locale),
            'discounts' => collect($order->discounts)->map(fn (array $discount): array => [
                'identifier' => $discount['identifier'],
                'name' => $discount['name'],
                'code' => $discount['code'] ?? null,
                'total' => Price::format(Price::fromArray($discount['total']), $order->currency, $locale),
            ])->all(),
            'shipping' => $order->shipping_option === null ? null : [
                'name' => $order->shipping_option_name,
                'price' => Price::format($order->shipping_price ?? Price::of(0), $order->currency, $locale),
            ],
            'tax' => $order->tax_total === null
                ? null
                : Price::format($order->tax_total, $order->currency, $locale),
            'items' => $order->items->map(function (OrderItem $item) use ($order, $locale): array {
                $discountTotal = $item->discount_total;
                $totalAfterDiscount = $discountTotal->greaterThan($item->total)
                    ? Price::of(0)
                    : $item->total->subtract($discountTotal);

                return [
                    'id' => $item->getKey(),
                    'name' => $item->product_name->get(),
                    'quantity' => $item->quantity,
                    'unitPrice' => Price::format($item->unit_price, $order->currency, $locale),
                    'total' => Price::format($item->total, $order->currency, $locale),
                    'discountTotal' => $discountTotal->isPositive()
                        ? Price::format($discountTotal, $order->currency, $locale)
                        : null,
                    'totalAfterDiscount' => Price::format($totalAfterDiscount, $order->currency, $locale),
                ];
            })->all(),
        ];
    }

    /** @return array<int, string>|null */
    private function addressLines(?Address $address): ?array
    {
        if ($address === null) {
            return null;
        }

        $lines = array_values(array_filter([
            trim($address->firstName.' '.$address->lastName),
            $address->company,
            ...$address->street,
            implode(', ', array_filter([$address->city, $address->state, $address->postcode])),
            $address->country,
        ], fn (?string $line): bool => filled($line)));

        return $lines === [] ? null : $lines;
    }
}
