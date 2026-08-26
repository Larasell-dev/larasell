<?php

namespace Larasell\Larasell\Checkout;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Larasell\Larasell\Address;
use Larasell\Larasell\Contracts\PaymentProvider;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\OrderNumbers\OrderNumberFactory;
use Larasell\Larasell\Payments\PaymentRequest;
use Larasell\Larasell\Payments\PaymentResult;

class Checkout
{
    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly OrderNumberFactory $orderNumbers,
        private readonly PaymentProvider $payments,
    ) {}

    /**
     * @param array{
     *     customer_email: string,
     *     customer_name: string,
     *     billing_address: Address|array<string, mixed>,
     *     shipping_address: Address|array<string, mixed>,
     *     customer_id?: int|null
     * } $data
     */
    public function create(Cart $cart, array $data): Order
    {
        $this->validate($data);
        $data['billing_address'] = $this->address($data['billing_address']);
        $data['shipping_address'] = $this->address($data['shipping_address']);

        $order = $this->database->transaction(function () use ($cart, $data): Order {
            /** @var Cart $lockedCart */
            $lockedCart = $cart->newQuery()->lockForUpdate()->findOrFail($cart->getKey());
            $items = $lockedCart->items()->with('product')->lockForUpdate()->get();
            $shippingOption = $lockedCart->shippingOption();

            if ($items->isEmpty()) {
                throw new InvalidArgumentException('Cannot checkout an empty cart.');
            }

            $total = null;

            foreach ($items as $item) {
                /** @var Product $product */
                $product = $item->product->newQuery()->lockForUpdate()->findOrFail($item->product_id);
                $item->setRelation('product', $product);
                $lockedCart->assertProductCanBePurchased($product, $item->quantity);

                $lineTotal = $item->total();
                $total = $total === null
                    ? $lineTotal
                    : $total->add($lineTotal);
            }

            /** @var class-string<Order> $orderModel */
            $orderModel = config('larasell.models.order', Order::class);
            $order = $orderModel::query()->create([
                'number' => $this->orderNumbers->generate(),
                'currency' => $lockedCart->currency,
                'customer_id' => $data['customer_id'] ?? null,
                'customer_email' => $data['customer_email'],
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'billing_address' => $data['billing_address'],
                'shipping_address' => $data['shipping_address'],
                'status' => OrderStatus::PendingPayment,
                'subtotal' => $total,
                'shipping_method' => $shippingOption?->method,
                'shipping_option' => $shippingOption?->handle,
                'shipping_option_name' => $shippingOption?->name,
                ...($shippingOption === null ? [] : ['shipping_price' => $shippingOption->price]),
                'total' => $shippingOption === null ? $total : $total->add($shippingOption->price),
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'product_id' => $item->product->getKey(),
                    'product_name' => $item->product->name,
                    'product_slug' => $item->product->slug,
                    'unit_price' => $item->product->price,
                    'quantity' => $item->quantity,
                    'total' => $item->total(),
                ]);

                if ($item->product->stock !== null) {
                    $item->product->decrement('stock', $item->quantity);
                }
            }

            $lockedCart->clear();

            return $order;
        });

        try {
            $result = $this->payments->pay(new PaymentRequest(
                $order->number,
                $order->total,
                $order->currency,
                $order->customer_email,
            ));
        } catch (\Throwable $exception) {
            $result = new PaymentResult(
                false,
                failureMessage: $exception->getMessage(),
            );
        }

        $order->payments()->create([
            'provider' => $this->payments::class,
            'reference' => $result->reference,
            'status' => $result->status === PaymentStatus::Pending
                ? PaymentStatus::Pending
                : ($result->successful ? PaymentStatus::Succeeded : PaymentStatus::Failed),
            'amount' => $order->total,
            'failure_message' => $result->failureMessage,
        ]);

        if ($result->status !== PaymentStatus::Pending) {
            $order->transitionTo($result->successful ? OrderStatus::Paid : OrderStatus::PaymentFailed);
        }

        return $order->load(['items', 'payments']);
    }

    /** @param array<string, mixed> $data */
    private function validate(array $data): void
    {
        if (! isset($data['customer_email']) || ! filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid customer email address is required.');
        }

        if (! isset($data['customer_name']) || trim((string) $data['customer_name']) === '') {
            throw new InvalidArgumentException('A customer name is required.');
        }

        if (isset($data['customer_phone']) && ! is_string($data['customer_phone'])) {
            throw new InvalidArgumentException('The customer phone must be a string or null.');
        }

        foreach (['billing_address', 'shipping_address'] as $type) {
            if (! isset($data[$type]) || (! is_array($data[$type]) && ! $data[$type] instanceof Address)) {
                throw new InvalidArgumentException("A {$type} is required.");
            }

            $this->address($data[$type]);
        }

        if (isset($data['customer_id']) && ! is_int($data['customer_id'])) {
            throw new InvalidArgumentException('The customer_id must be an integer or null.');
        }
    }

    /** @param Address|array<string, mixed> $address */
    private function address(Address|array $address): Address
    {
        return $address instanceof Address ? $address : Address::fromArray($address);
    }
}
