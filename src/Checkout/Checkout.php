<?php

namespace Larasell\Larasell\Checkout;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Larasell\Larasell\Address;
use Larasell\Larasell\Cart\Exceptions\EmptyCartException;
use Larasell\Larasell\Cart\Exceptions\InvalidCartItemException;
use Larasell\Larasell\Cart\Exceptions\MissingRequiredShippingAddressException;
use Larasell\Larasell\Contracts\PaymentProvider;
use Larasell\Larasell\Contracts\Promotions\PromotionCustomerResolver;
use Larasell\Larasell\Discounts\DiscountAllocation;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Enums\InventoryReservationStatus;
use Larasell\Larasell\Enums\OrderStatus;
use Larasell\Larasell\Enums\PaymentStatus;
use Larasell\Larasell\Enums\PromotionRedemptionStatus;
use Larasell\Larasell\Enums\TaxResultStatus;
use Larasell\Larasell\Events\InventoryDecremented;
use Larasell\Larasell\Events\InventoryReserved;
use Larasell\Larasell\Events\OrderPlaced;
use Larasell\Larasell\Events\PromotionApplied;
use Larasell\Larasell\Events\PromotionRedemptionReserved;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\ModelRegistry;
use Larasell\Larasell\Models\Order;
use Larasell\Larasell\Models\Payment;
use Larasell\Larasell\Models\ProductVariant;
use Larasell\Larasell\OrderNumbers\OrderNumberFactory;
use Larasell\Larasell\Payments\PaymentBreakdownFactory;
use Larasell\Larasell\Payments\PaymentManager;
use Larasell\Larasell\Payments\PaymentRequest;
use Larasell\Larasell\Price;
use Larasell\Larasell\Promotions\PromotionRedemptionCounters;
use Larasell\Larasell\Taxes\CartTaxEstimator;
use Larasell\Larasell\Taxes\Exceptions\TaxCalculationException;
use Larasell\Larasell\Taxes\TaxLineResult;
use Larasell\Larasell\Taxes\TaxSnapshotFactory;

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
        private readonly PaymentBreakdownFactory $paymentBreakdowns,
        private readonly CartTaxEstimator $taxes,
        private readonly TaxSnapshotFactory $taxSnapshots,
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
        ?string $idempotencyKey = null,
    ): CheckoutResult {
        $this->validate($data);
        $this->validateIdempotencyKey($idempotencyKey);
        $method = $paymentMethod === null
            ? $this->payments->default()
            : $this->payments->method($paymentMethod);
        $provider = $this->payments->provider($method);
        $data['billing_address'] = $this->address($data['billing_address'] ?? null);
        $data['shipping_address'] = $this->address($data['shipping_address'] ?? null);
        $existingOrder = $idempotencyKey === null
            ? null
            : $this->models->order->query()->where('idempotency_key', $idempotencyKey)->first();
        $fingerprint = $idempotencyKey === null
            ? null
            : $this->fingerprint($cart, $data, $method->handle, $paymentOptions, $existingOrder);

        if ($idempotencyKey !== null
            && ($existing = $this->existingCheckout($idempotencyKey, $fingerprint)) !== null) {
            return $this->initiate($existing[0], $existing[1], $provider, $method->handle, $paymentOptions, false);
        }

        try {
            [$order, $payment, $created] = $this->database->transaction(function () use (
                $cart,
                $data,
                $method,
                $idempotencyKey,
                $fingerprint,
            ): array {
                /** @var Cart $lockedCart */
                $lockedCart = $cart->newQuery()->lockForUpdate()->findOrFail($cart->getKey());

                if ($idempotencyKey !== null
                    && ($existing = $this->existingCheckout($idempotencyKey, $fingerprint)) !== null) {
                    return [$existing[0], $existing[1], false];
                }

                $items = $lockedCart->items()
                    ->with(['product', 'variant.product'])
                    ->orderBy('product_variant_id')
                    ->lockForUpdate()
                    ->get();
                $shippingOption = $lockedCart->shippingOption();

                if ($shippingOption?->requiresAddress && $data['shipping_address'] === null) {
                    throw new MissingRequiredShippingAddressException($shippingOption->handle);
                }

                if ($items->isEmpty()) {
                    throw new EmptyCartException;
                }

                $total = Price::of(0);

                $variants = $this->models->productVariant->query()
                    ->with(['product.variantDimensions', 'attributeValues.attribute'])
                    ->whereKey($items->pluck('product_variant_id'))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy(fn (ProductVariant $variant): int => (int) $variant->getKey());

                foreach ($items->groupBy('product_variant_id') as $variantId => $variantItems) {
                    /** @var ProductVariant|null $variant */
                    $variant = $variants->get($variantId);

                    if ($variant !== null) {
                        $lockedCart->assertVariantCanBePurchased($variant, (int) $variantItems->sum('quantity'));
                    }
                }

                foreach ($items as $item) {
                    /** @var ProductVariant|null $variant */
                    $variant = $variants->get($item->product_variant_id);

                    if ($variant === null || $variant->product_id !== $item->product_id) {
                        throw new InvalidCartItemException;
                    }

                    $item->setRelation('variant', $variant);
                    $item->setRelation('product', $variant->product);
                    $lockedCart->assertVariantCanBePurchased($variant, $item->quantity);

                    $lineTotal = $item->total();
                    $total = $total->add($lineTotal);
                }

                $discounts = $this->promotions->apply($lockedCart)
                    ->filter(fn (DiscountResult $discount): bool => $discount->total()->isPositive())
                    ->values();
                $discountTotal = $discounts->reduce(
                    fn (Price $sum, DiscountResult $discount): Price => $sum->add($discount->total()),
                    Price::of(0),
                );
                $taxEstimate = $this->taxes->estimate(
                    cart: $lockedCart,
                    shippingAddress: $data['shipping_address'],
                    billingAddress: $data['billing_address'],
                    customerIdentifier: isset($data['customer_id'])
                        ? (string) $data['customer_id']
                        : $data['customer_email'],
                    metadata: ['checkout' => true],
                    discounts: $discounts,
                );

                if ($taxEstimate->tax->status !== TaxResultStatus::Calculated) {
                    throw new TaxCalculationException(
                        $taxEstimate->tax->reason ?? 'Checkout requires an authoritative tax calculation.'
                    );
                }

                $orderTotal = $taxEstimate->total();

                if ($orderTotal === null) {
                    throw new TaxCalculationException('Checkout could not determine an authoritative total including tax.');
                }

                $taxLines = [];

                foreach ($taxEstimate->tax->lines as $taxLine) {
                    $taxLines[$taxLine->lineIdentifier] = $taxLine;
                }

                $shippingTaxLine = $shippingOption === null ? null : ($taxLines['shipping'] ?? null);

                if ($shippingOption !== null && ! $shippingTaxLine instanceof TaxLineResult) {
                    throw new TaxCalculationException('The tax calculation did not return the shipping line.');
                }

                $order = $this->models->order->query()->create([
                    'number' => $this->orderNumbers->generate(),
                    'idempotency_key' => $idempotencyKey,
                    'idempotency_fingerprint' => $fingerprint,
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
                    'tax_price_mode' => $taxEstimate->tax->priceMode,
                    'tax_total' => $taxEstimate->tax->taxAmount(),
                    'tax_snapshot' => $this->taxSnapshots->order($taxEstimate->tax),
                    'shipping_method' => $shippingOption?->method,
                    'shipping_option' => $shippingOption?->handle,
                    'shipping_option_name' => $shippingOption?->name,
                    'metadata' => $lockedCart->metadata,
                    ...($shippingOption === null ? [] : ['shipping_price' => $shippingOption->price]),
                    ...($shippingTaxLine === null ? [] : [
                        'shipping_tax_total' => $shippingTaxLine->taxAmount,
                        'shipping_tax_snapshot' => $this->taxSnapshots->line($shippingTaxLine, $taxEstimate->tax->priceMode),
                    ]),
                    'total' => $orderTotal,
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
                                ->firstWhere('target', $target)->amount ?? Price::of(0)
                        ),
                        Price::of(0),
                    );
                    $taxLine = $taxLines[$target] ?? null;

                    if (! $taxLine instanceof TaxLineResult) {
                        throw new TaxCalculationException("The tax calculation did not return cart line [{$target}].");
                    }

                    $orderItem = $order->items()->create([
                        'product_id' => $item->product->getKey(),
                        'product_variant_id' => $item->variant->getKey(),
                        'product_name' => $item->product->name,
                        'product_slug' => $item->product->slug->get(),
                        'product_sku' => $item->sku(),
                        'product_barcode' => $item->barcode(),
                        'variant_name' => $item->variant->snapshotName(),
                        'variant_options' => $item->variant->optionSnapshot(),
                        'unit_price' => $item->unitPrice(),
                        'quantity' => $item->quantity,
                        'inventory_quantity' => $item->availableStock() === null ? 0 : $item->quantity,
                        'metadata' => $item->metadata,
                        'discount_total' => $lineDiscountTotal,
                        'tax_category' => $taxLine->category,
                        'taxable_amount' => $taxLine->taxableAmount,
                        'tax_total' => $taxLine->taxAmount,
                        'tax_snapshot' => $this->taxSnapshots->line($taxLine, $taxEstimate->tax->priceMode),
                        'total' => $item->total(),
                    ]);
                    $orderItemsByTarget[$target] = $orderItem->getKey();

                    if ($item->availableStock() !== null) {
                        $item->variant->decrementInventory($item->quantity);
                        $reservation = $orderItem->inventoryReservation()->create([
                            'order_id' => $order->getKey(),
                            'product_id' => $item->product->getKey(),
                            'product_variant_id' => $item->variant->getKey(),
                            'quantity' => $item->quantity,
                            'status' => InventoryReservationStatus::Active,
                            'expires_at' => $method->inventoryReservationMinutes === null
                                ? null
                                : now()->addMinutes($method->inventoryReservationMinutes),
                        ]);
                        InventoryDecremented::dispatch($item->product, $order, $item->quantity, $item->variant);
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

                return [$order, $payment, true];
            });
        } catch (QueryException $exception) {
            if ($idempotencyKey === null
                || ($existing = $this->existingCheckout($idempotencyKey, $fingerprint)) === null) {
                throw $exception;
            }

            [$order, $payment] = $existing;
            $created = false;
        }

        return $this->initiate($order, $payment, $provider, $method->handle, $paymentOptions, $created);
    }

    /** @param array<string, mixed> $paymentOptions */
    private function initiate(
        Order $order,
        Payment $payment,
        PaymentProvider $provider,
        string $paymentMethod,
        array $paymentOptions,
        bool $created,
    ): CheckoutResult {
        $order->loadMissing(['items', 'payments']);

        if ($payment->status !== PaymentStatus::Pending) {
            return new CheckoutResult($order, $payment);
        }

        try {
            $result = $provider->initiate(new PaymentRequest(
                $paymentMethod,
                $order,
                $payment,
                $this->paymentBreakdowns->make($order, $payment),
                $paymentOptions,
            ));
        } catch (\Throwable $exception) {
            if ($created) {
                OrderPlaced::dispatch($order);
            }

            throw $exception;
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

        if ($created) {
            OrderPlaced::dispatch($order);
        }

        return new CheckoutResult($order->refresh()->load(['items', 'payments']), $payment, $result->action);
    }

    /** @return array{Order, Payment}|null */
    private function existingCheckout(string $key, ?string $fingerprint): ?array
    {
        if ($fingerprint === null) {
            throw new InvalidArgumentException('An idempotency fingerprint is required.');
        }

        /** @var Order|null $order */
        $order = $this->models->order->query()->where('idempotency_key', $key)->first();

        if ($order === null) {
            return null;
        }

        if (! hash_equals((string) $order->idempotency_fingerprint, $fingerprint)) {
            throw new InvalidArgumentException('The idempotency key has already been used with different checkout input.');
        }

        return [$order, $order->payments()->oldest('id')->firstOrFail()];
    }

    private function validateIdempotencyKey(?string $key): void
    {
        if ($key !== null && (trim($key) === '' || strlen($key) > 255)) {
            throw new InvalidArgumentException('The idempotency key must be a non-empty string of at most 255 characters.');
        }
    }

    /** @param array<string, mixed> $data
     * @param  array<string, mixed>  $paymentOptions
     */
    private function fingerprint(
        Cart $cart,
        array $data,
        string $paymentMethod,
        array $paymentOptions,
        ?Order $existingOrder = null,
    ): string {
        $items = $cart->items()
            ->orderBy('product_variant_id')
            ->get(['product_variant_id', 'quantity'])
            ->map(fn ($item): array => [
                'product_variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity,
            ])
            ->all();

        if ($items === [] && $existingOrder !== null) {
            $items = $existingOrder->items()
                ->orderBy('product_variant_id')
                ->get(['product_variant_id', 'quantity'])
                ->map(fn ($item): array => [
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => $item->quantity,
                ])
                ->all();
        }

        $input = [
            'cart_type' => $cart->getMorphClass(),
            'cart_id' => $cart->getKey(),
            'metadata' => $cart->metadata,
            'items' => $items,
            'data' => $data,
            'payment_method' => $paymentMethod,
            'payment_options' => $paymentOptions,
        ];

        return hash('sha256', json_encode($this->canonicalize($input), JSON_THROW_ON_ERROR));
    }

    private function canonicalize(mixed $value): mixed
    {
        if ($value instanceof Address) {
            return $this->canonicalize($value->toArray());
        }

        if ($value instanceof Collection) {
            return $this->canonicalize($value->all());
        }

        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
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

        $limits = $discount->redemptionLimits;

        if ($limits === null) {
            throw new InvalidArgumentException('Promotion redemption limits are required.');
        }

        $this->promotionRedemptions->reserve(
            $discount->identifier,
            $customerIdentifier,
            $limits,
            $now,
        );

        $redemption = $order->promotionRedemptions()->create([
            'promotion_identifier' => $discount->identifier,
            'customer_identifier' => $customerIdentifier,
            'global_limit' => $limits->global,
            'customer_limit' => $limits->customer,
            'status' => PromotionRedemptionStatus::Reserved,
            'expires_at' => $reservationMinutes === null ? null : $now->copy()->addMinutes($reservationMinutes),
        ]);
        PromotionRedemptionReserved::dispatch($redemption);
    }
}
