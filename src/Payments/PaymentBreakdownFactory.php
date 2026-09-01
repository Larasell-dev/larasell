<?php

namespace Larasell\Larasell\Payments;

use Larasell\Larasell\Enums\TaxPriceMode;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\OrderItem;
use Larasell\Larasell\Models\Payment;
use Larasell\Larasell\Price;

final class PaymentBreakdownFactory
{
    public function make(Order $order, Payment $payment): PaymentBreakdown
    {
        $order->loadMissing('items');

        $lines = array_values($order->items
            ->map(fn (OrderItem $item): PaymentLine => new PaymentLine(
                identifier: 'order-item:'.$item->getKey(),
                name: $item->product_name->get(),
                quantity: $item->quantity,
                amount: $this->payableAmount(
                    $item->total->subtract($item->discount_total),
                    $item->tax_total,
                    $order->tax_price_mode,
                ),
                metadata: array_filter([
                    'order_item_id' => $item->getKey(),
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'sku' => $item->product_sku,
                    'barcode' => $item->product_barcode,
                    'variant_name' => $item->variant_name,
                    'variant_options' => $item->variant_options,
                ], fn (mixed $value): bool => $value !== null),
            ))
            ->values()
            ->all());

        return new PaymentBreakdown(
            lines: $lines,
            shipping: $this->shipping($order),
            total: $payment->amount,
        );
    }

    private function shipping(Order $order): ?PaymentLine
    {
        if ($order->getRawOriginal('shipping_price') === null) {
            return null;
        }

        $shippingPrice = $order->shipping_price;

        if (! $shippingPrice instanceof Price) {
            throw new \LogicException('An order with shipping must have a shipping price.');
        }

        $discount = collect($order->discounts)
            ->flatMap(fn (array $snapshot): array => $snapshot['allocations'] ?? [])
            ->where('target', 'shipping')
            ->reduce(
                fn (Price $total, array $allocation): Price => $total->add(Price::fromArray($allocation['amount'])),
                Price::of(0),
            );
        $amount = $this->payableAmount(
            $shippingPrice->subtract($discount),
            $order->shipping_tax_total,
            $order->tax_price_mode,
        );

        return new PaymentLine(
            identifier: 'shipping',
            name: $order->shipping_option_name ?? 'Shipping',
            quantity: 1,
            amount: $amount,
            metadata: array_filter([
                'method' => $order->shipping_method,
                'option' => $order->shipping_option,
            ], fn (mixed $value): bool => $value !== null),
        );
    }

    private function payableAmount(Price $amount, ?Price $tax, ?TaxPriceMode $priceMode): Price
    {
        return $priceMode === TaxPriceMode::Exclusive && $tax !== null
            ? $amount->add($tax)
            : $amount;
    }
}
