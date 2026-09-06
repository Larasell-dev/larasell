<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;
use Larasell\Larasell\Address;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\OrderItem;
use Larasell\Larasell\Price;

class OrderController extends Controller
{
    public function show(string $publicId): Response
    {
        $order = Order::query()
            ->with('items')
            ->where('public_id', $publicId)
            ->firstOrFail();

        $locale = App::currentLocale();
        $billingAddress = $this->addressLines($order->billing_address);
        $shippingAddress = $this->addressLines($order->shipping_address);
        $sameAddress = $billingAddress !== null && $billingAddress === $shippingAddress;

        return Inertia::render('OrderConfirmation', [
            'order' => [
                'number' => $order->number,
                'customerEmail' => $order->customer_email,
                'customerName' => $order->customer_name,
                'billingAddress' => $sameAddress ? null : $billingAddress,
                'shippingAddress' => $shippingAddress,
                'status' => $order->status->value,
                'subtotal' => Price::format($order->subtotal, $order->currency, $locale),
                'discounts' => collect($order->discounts)->map(fn (array $discount): array => [
                    'identifier' => $discount['identifier'],
                    'name' => $discount['name'],
                    'code' => $discount['code'] ?? null,
                    'total' => Price::format(Price::fromArray($discount['total']), $order->currency, $locale),
                ])->all(),
                'total' => Price::format($order->total, $order->currency, $locale),
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
            ],
        ]);
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
