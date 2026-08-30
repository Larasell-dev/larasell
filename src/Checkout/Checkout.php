<?php

namespace Larasell\Larasell\Checkout;

use Illuminate\Database\ConnectionInterface;
use InvalidArgumentException;
use Larasell\Larasell\Address;
use Larasell\Larasell\Contracts\Promotions\PromotionCustomerResolver;
use Larasell\Larasell\Discounts\DiscountAllocation;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Enums\InventoryReservationStatus;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Enums\PromotionRedemptionStatus;
use Larasell\Larasell\Events\InventoryDecremented;
use Larasell\Larasell\Events\InventoryReserved;
use Larasell\Larasell\Events\OrderPlaced;
use Larasell\Larasell\Events\PromotionApplied;
use Larasell\Larasell\Events\PromotionRedemptionReserved;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\ModelRegistry;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Product;
use Larasell\Larasell\OrderNumbers\OrderNumberFactory;
use Larasell\Larasell\Payments\PaymentManager;
use Larasell\Larasell\Payments\PaymentRequest;
use Larasell\Larasell\Payments\PaymentResult;
use Larasell\Larasell\Price;
use Larasell\Larasell\Promotions\PromotionRedemptionCounters;

class Checkout
{
    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly ModelRegistry $models,
        private readonly OrderNumberFactory $orderNumbers,
        private readonly PaymentManager $payments,
        private readonly PromotionManager $promotions,
        private readonly PromotionCustomerResolver $promotionCustomers,
        private readonly PromotionRedemptionCounters $promotionRedemptions,
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
     * @param  array<string, mixed>  $paymentOptions
     */
    public function create(
        Cart $cart,
        array $data,
        ?string $paymentMethod = null,
        array $paymentOptions = [],
    ): CheckoutResult {
        $this->validate($data);
        $method = $paymentMethod === null
            ? $this->payments->default()
            : $this->payments->method($paymentMethod);
        $provider = $this->payments->provider($method);
        $data['billing_address'] = $this->address($data['billing_address'] ?? null);
        $data['shipping_address'] = $this->address($data['shipping_address'] ?? null);

        [$order, $payment] = $this->database->transaction(function () use ($cart, $data, $method): array {
            /** @var Cart $lockedCart */
            $lockedCart = $cart->newQuery()->lockForUpdate()->findOrFail($cart->getKey());
            $items = $lockedCart->items()
                ->with('product')
                ->orderBy('product_id')
                ->lockForUpdate()
                ->get();
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

            $discounts = $this->promotions->apply($lockedCart)
                ->filter(fn (DiscountResult $discount): bool => $discount->total()->isPositive())
                ->values();
            $discountTotal = $discounts->reduce(
                fn (Price $sum, DiscountResult $discount): Price => $sum->add($discount->total()),
                Price::of(0),
            );
            $totalBeforeDiscounts = $shippingOption === null ? $total : $total->add($shippingOption->price);

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
                'discount_total' => $discountTotal,
                'discounts' => [],
                'shipping_method' => $shippingOption?->method,
                'shipping_option' => $shippingOption?->handle,
                'shipping_option_name' => $shippingOption?->name,
                ...($shippingOption === null ? [] : ['shipping_price' => $shippingOption->price]),
                'total' => $totalBeforeDiscounts->subtract($discountTotal),
            ]);

            foreach ($discounts->sortBy('identifier') as $discount) {
                if ($discount->redemptionLimits !== null) {
                    $this->reservePromotionRedemption($order, $discount, $data, $method->inventoryReservationMinutes);
                }
            }

            $orderItemsByTarget = [];

            foreach ($items as $item) {
                $target = 'line:'.$item->getKey();
                $lineDiscountTotal = $discounts->reduce(
                    fn (Price $sum, DiscountResult $discount): Price => $sum->add(
                        collect($discount->allocations)
                            ->firstWhere('target', $target)?->amount ?? Price::of(0)
                    ),
                    Price::of(0),
                );
                $orderItem = $order->items()->create([
                    'product_id' => $item->product->getKey(),
                    'product_name' => $item->product->name->get(),
                    'product_slug' => $item->product->slug,
                    'unit_price' => $item->product->price,
                    'quantity' => $item->quantity,
                    'inventory_quantity' => $item->product->stock === null ? 0 : $item->quantity,
                    'discount_total' => $lineDiscountTotal,
                    'total' => $item->total(),
                ]);
                $orderItemsByTarget[$target] = $orderItem->getKey();

                if ($item->product->stock !== null) {
                    $item->product->decrement('stock', $item->quantity);
                    $reservation = $orderItem->inventoryReservation()->create([
                        'order_id' => $order->getKey(),
                        'product_id' => $item->product->getKey(),
                        'quantity' => $item->quantity,
                        'status' => InventoryReservationStatus::Active,
                        'expires_at' => $method->inventoryReservationMinutes === null
                            ? null
                            : now()->addMinutes($method->inventoryReservationMinutes),
                    ]);
                    InventoryDecremented::dispatch($item->product, $order, $item->quantity);
                    InventoryReserved::dispatch($reservation);
                }
            }

            $discountSnapshots = $discounts->map(fn (DiscountResult $discount): array => [
                'identifier' => $discount->identifier,
                'name' => $discount->name,
                ...($discount->code === null ? [] : ['code' => $discount->code]),
                'total' => $discount->total()->toArray(),
                'allocations' => collect($discount->allocations)->map(
                    fn (DiscountAllocation $allocation): array => [
                        'target' => $allocation->target === 'shipping' ? 'shipping' : 'line',
                        'order_item_id' => $orderItemsByTarget[$allocation->target] ?? null,
                        'amount' => $allocation->amount->toArray(),
                    ]
                )->all(),
            ])->all();
            $order->update(['discounts' => $discountSnapshots]);

            foreach ($discountSnapshots as $discountSnapshot) {
                PromotionApplied::dispatch($order, $discountSnapshot);
            }

            $lockedCart->clear();

            $payment = $order->payments()->create([
                'method' => $method->handle,
                'provider' => $method->driver,
                'status' => PaymentStatus::Pending,
                'amount' => $order->total,
            ]);

            return [$order, $payment];
        });

        try {
            $result = $provider->initiate(new PaymentRequest(
                $method->handle,
                $order,
                $payment,
                $paymentOptions,
            ));
        } catch (\Throwable $exception) {
            $result = PaymentResult::failed($exception->getMessage());
        }

        if ($result->reference !== null) {
            $payment->update(['reference' => $result->reference]);
        }

        $payment = match ($result->status) {
            PaymentStatus::Pending => $payment->refresh(),
            PaymentStatus::Succeeded => $payment->markAsPaid(),
            PaymentStatus::Failed => $payment->markAsFailed($result->failureMessage),
            PaymentStatus::Cancelled => $payment->cancel(),
        };

        if ($result->status === PaymentStatus::Cancelled) {
            $order->cancel();
        }

        $order->load(['items', 'payments']);
        OrderPlaced::dispatch($order);

        return new CheckoutResult($order->refresh()->load(['items', 'payments']), $payment, $result->action);
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

    /**
     * @param  array{customer_email: string, customer_id?: int|null}  $data
     */
    private function reservePromotionRedemption(
        Order $order,
        DiscountResult $discount,
        array $data,
        ?int $reservationMinutes,
    ): void {
        $now = now();
        $customerIdentifier = $this->promotionCustomers->resolve(
            $data['customer_id'] ?? null,
            $data['customer_email'],
        );

        if (trim($customerIdentifier) === '') {
            throw new InvalidArgumentException('The promotion customer resolver must return a customer identifier.');
        }

        $this->promotionRedemptions->reserve(
            $discount->identifier,
            $customerIdentifier,
            $discount->redemptionLimits,
            $now,
        );

        $redemption = $order->promotionRedemptions()->create([
            'promotion_identifier' => $discount->identifier,
            'customer_identifier' => $customerIdentifier,
            'global_limit' => $discount->redemptionLimits->global,
            'customer_limit' => $discount->redemptionLimits->customer,
            'status' => PromotionRedemptionStatus::Reserved,
            'expires_at' => $reservationMinutes === null ? null : $now->copy()->addMinutes($reservationMinutes),
        ]);
        PromotionRedemptionReserved::dispatch($redemption);
    }
}
