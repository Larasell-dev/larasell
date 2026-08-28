<?php

namespace Larasell\Larasell\Checkout;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Larasell\Larasell\Address;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\ModelRegistry;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\OrderNumbers\OrderNumberFactory;
use Larasell\Larasell\Payments\PaymentManager;
use Larasell\Larasell\Payments\PaymentRequest;
use Larasell\Larasell\Payments\PaymentResult;

class Checkout
{
    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly ModelRegistry $models,
        private readonly OrderNumberFactory $orderNumbers,
        private readonly PaymentManager $payments,
    ) {}

    /**
     * @param array{
     *     customer_email: string,
     *     customer_name: string,
     *     customer_phone?: string|null,
     *     billing_address?: Address|array<string, mixed>|null,
     *     shipping_address?: Address|array<string, mixed>|null,
     *     customer_id?: int|null
     * } $data
     */
    public function create(Cart $cart, array $data, ?string $paymentMethod = null): Order
    {
        $this->validate($data);
        $method = $paymentMethod === null
            ? $this->payments->default()
            : $this->payments->method($paymentMethod);
        $provider = $this->payments->provider($method);
        $data['billing_address'] = $this->address($data['billing_address'] ?? null);
        $data['shipping_address'] = $this->address($data['shipping_address'] ?? null);

        $order = $this->database->transaction(function () use ($cart, $data): Order {
            /** @var Cart $lockedCart */
            $lockedCart = $cart->newQuery()->lockForUpdate()->findOrFail($cart->getKey());
            $items = $lockedCart->items()->with('product')->lockForUpdate()->get();
            $shippingOption = $lockedCart->shippingOption();

            if ($shippingOption?->requiresAddress && $data['shipping_address'] === null) {
                throw new InvalidArgumentException('A shipping_address is required for the selected shipping option.');
            }

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

            $order = $this->models->order->query()->create([
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
                    'product_name' => $item->product->name->get(),
                    'product_slug' => $item->product->slug,
                    'unit_price' => $item->product->price,
                    'quantity' => $item->quantity,
                    'inventory_quantity' => $item->product->stock === null ? 0 : $item->quantity,
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
            $result = $provider->initiate(new PaymentRequest(
                $method->handle,
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
            'method' => $method->handle,
            'provider' => $method->driver,
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
            if (! isset($data[$type])) {
                continue;
            }

            if (! is_array($data[$type]) && ! $data[$type] instanceof Address) {
                throw new InvalidArgumentException("The {$type} must be an address or null.");
            }

            $this->address($data[$type]);
        }

        if (isset($data['customer_id']) && ! is_int($data['customer_id'])) {
            throw new InvalidArgumentException('The customer_id must be an integer or null.');
        }
    }

    /** @param Address|array<string, mixed>|null $address */
    private function address(Address|array|null $address): ?Address
    {
        if ($address === null) {
            return null;
        }

        return $address instanceof Address ? $address : Address::fromArray($address);
    }
}
