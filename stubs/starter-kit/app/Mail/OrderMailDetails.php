<?php

namespace App\Mail;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Larasell\Larasell\Address;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\OrderItem;
use Larasell\Larasell\Models\Payment;
use Larasell\Larasell\Price;

final class OrderMailDetails
{
    /**
     * @return array{
     *     address: array<int, string>|null,
     *     billingAddress: array<int, string>|null,
     *     cancellationMessage: string|null,
     *     customerName: string,
     *     discounts: array<int, array{label: string, total: string}>,
     *     intro: string,
     *     items: array<int, array{line: string}>,
     *     number: string,
     *     shipping: string|null,
     *     shippingAddress: array<int, string>|null,
     *     subtotal: string,
     *     tax: string|null,
     *     total: string,
     *     url: string
     * }
     */
    public function toArray(Order $order): array
    {
        $order->loadMissing(['items', 'payments']);

        $locale = App::currentLocale();
        $billingAddress = $this->addressLines($order->billing_address);
        $shippingAddress = $this->addressLines($order->shipping_address);
        $sameAddress = $billingAddress !== null && $billingAddress === $shippingAddress;
        $paymentMethod = $this->paymentMethod($order->payments->first());

        return [
            'number' => $order->number,
            'customerName' => $order->customer_name,
            'url' => route('orders.confirmation', $order->public_id),
            'intro' => $this->receivedIntro($order, $paymentMethod),
            'cancellationMessage' => $this->cancellationMessage($order->cancellation_reason),
            'items' => $order->items->map(function (OrderItem $item) use ($order, $locale): array {
                $discountTotal = $item->discount_total;
                $totalAfterDiscount = $discountTotal->greaterThan($item->total)
                    ? Price::of(0)
                    : $item->total->subtract($discountTotal);
                $name = $item->product_name->get();

                if (filled($item->variant_name)) {
                    $name .= ' — '.$item->variant_name;
                }

                $line = $item->quantity.' × '.$name.' — '.Price::format($totalAfterDiscount, $order->currency, $locale);

                if ($discountTotal->isPositive()) {
                    $line .= ' (was '.Price::format($item->total, $order->currency, $locale).')';
                }

                return [
                    'line' => $line,
                ];
            })->all(),
            'subtotal' => Price::format($order->subtotal, $order->currency, $locale),
            'discounts' => collect($order->discounts)->map(fn (array $discount): array => [
                'label' => filled($discount['code'] ?? null)
                    ? $discount['name'].' ('.$discount['code'].')'
                    : $discount['name'],
                'total' => Price::format(Price::fromArray($discount['total']), $order->currency, $locale),
            ])->all(),
            'shipping' => $this->shipping($order, $locale),
            'tax' => $order->tax_total === null
                ? null
                : Price::format($order->tax_total, $order->currency, $locale),
            'total' => Price::format($order->total, $order->currency, $locale),
            'address' => $sameAddress ? $shippingAddress : null,
            'billingAddress' => $sameAddress ? null : $billingAddress,
            'shippingAddress' => $sameAddress ? null : $shippingAddress,
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

    private function receivedIntro(Order $order, ?string $paymentMethod): string
    {
        if ($order->status === OrderStatus::PendingPayment) {
            $pending = $paymentMethod === null
                ? 'Thank you for your order. Payment is still pending.'
                : "Thank you for your order. Payment is still pending ({$paymentMethod}).";

            return $pending." We'll email you again when we've received it.";
        }

        return $paymentMethod === null
            ? "Thank you for your order. We've received your payment."
            : "Thank you for your order. We've received your payment via {$paymentMethod}.";
    }

    private function paymentMethod(?Payment $payment): ?string
    {
        if ($payment === null || $payment->method === '') {
            return null;
        }

        return Str::headline($payment->method);
    }

    private function shipping(Order $order, string $locale): ?string
    {
        if ($order->shipping_option === null) {
            return null;
        }

        $price = Price::format($order->shipping_price, $order->currency, $locale);
        $name = $order->shipping_option_name;

        return filled($name) ? $name.' — '.$price : $price;
    }

    private function cancellationMessage(?string $reason): ?string
    {
        return match ($reason) {
            'Inventory reservation expired' => 'This unpaid order was cancelled because the reservation expired before payment was completed.',
            'Promotion redemption expired' => 'This unpaid order was cancelled because the promotion reservation expired before payment was completed.',
            null, '' => null,
            default => $reason,
        };
    }
}
