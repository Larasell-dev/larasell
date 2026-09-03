<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;
use Larasell\Larasell\Address;
use Larasell\Larasell\Cart\Exceptions\CartQuantityBelowMinimumException;
use Larasell\Larasell\Cart\Exceptions\CartQuantityExceedsMaximumException;
use Larasell\Larasell\Cart\Exceptions\InsufficientCartStockException;
use Larasell\Larasell\Cart\Exceptions\InvalidCartItemException;
use Larasell\Larasell\Cart\Exceptions\InvalidCartQuantityException;
use Larasell\Larasell\Cart\Exceptions\StaleSelectedShippingOptionException;
use Larasell\Larasell\Cart\Exceptions\UnavailableCartItemException;
use Larasell\Larasell\Cart\Exceptions\UnavailableShippingOptionException;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Events\PromotionCodeRemoved;
use Larasell\Larasell\Price;
use Larasell\Larasell\Shipping\ShippingManager;
use Larasell\Larasell\Shipping\ShippingOption;
use Larasell\Larasell\Taxes\CartTaxEstimate;
use Larasell\Larasell\Taxes\CartTaxEstimator;

/**
 * @property int $id
 * @property Currency $currency
 * @property string|null $session_id
 * @property int|null $user_id
 * @property string|null $shipping_option
 * @property array<int, string> $promotion_codes
 * @property SupportCollection<string, mixed> $metadata
 * @property Collection<int, CartItem> $items
 */
class Cart extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $table = 'larasell_carts';

    protected $guarded = [];

    protected $attributes = [
        'metadata' => '[]',
        'promotion_codes' => '[]',
    ];

    protected $casts = [
        'currency' => Currency::class,
        'metadata' => AsCollection::class,
        'promotion_codes' => 'array',
        'user_id' => 'integer',
    ];

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany($this->cartItemModel());
    }

    /** @param array<string, mixed> $metadata */
    public function add(Product|ProductVariant $purchasable, int $quantity = 1, array $metadata = []): CartItem
    {
        $this->assertValidQuantity($quantity);
        $addedQuantity = $quantity;
        $variant = $this->resolveVariant($purchasable);
        $metadata = $this->normalizeMetadata($metadata);

        $item = $this->items()
            ->where('product_variant_id', $variant->getKey())
            ->get()
            ->first(fn (CartItem $item): bool => $this->normalizeMetadata($item->metadata->all()) === $metadata);

        $quantity = $quantity + ($item->quantity ?? 0);
        $this->assertVariantCanBePurchased($variant, $quantity);
        $this->assertVariantCanBePurchased(
            $variant,
            (int) $this->items()->where('product_variant_id', $variant->getKey())->sum('quantity') + $addedQuantity,
        );

        if ($item !== null) {
            $item->update(['quantity' => $quantity]);

            return $item;
        }

        return $this->items()->create([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->getKey(),
            'quantity' => $quantity,
            'metadata' => $metadata,
        ]);
    }

    /** @param array<string, mixed> $metadata */
    public function set(Product|ProductVariant $purchasable, int $quantity, array $metadata = []): CartItem
    {
        $this->assertValidQuantity($quantity);
        $variant = $this->resolveVariant($purchasable);
        $this->assertVariantCanBePurchased($variant, $quantity);
        $metadata = $this->normalizeMetadata($metadata);

        $item = $this->items()
            ->where('product_variant_id', $variant->getKey())
            ->get()
            ->first(fn (CartItem $item): bool => $this->normalizeMetadata($item->metadata->all()) === $metadata);
        $variantQuantity = (int) $this->items()
            ->where('product_variant_id', $variant->getKey())
            ->sum('quantity');
        $this->assertVariantCanBePurchased($variant, $variantQuantity - ($item->quantity ?? 0) + $quantity);

        if ($item !== null) {
            $item->update(['quantity' => $quantity]);

            return $item;
        }

        return $this->items()->create([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->getKey(),
            'quantity' => $quantity,
            'metadata' => $metadata,
        ]);
    }

    /** @param array<string, mixed> $metadata */
    public function remove(Product|ProductVariant $purchasable, array $metadata = []): void
    {
        $variant = $this->resolveVariant($purchasable);
        $metadata = $this->normalizeMetadata($metadata);

        $this->items()
            ->where('product_variant_id', $variant->getKey())
            ->get()
            ->filter(fn (CartItem $item): bool => $this->normalizeMetadata($item->metadata->all()) === $metadata)
            ->each->delete();
    }

    public function clear(): void
    {
        $this->items()->delete();
    }

    public function quantity(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    /** @return Collection<int, CartItem> */
    public function purchasableItems(): Collection
    {
        return $this->items()
            ->with(['product', 'variant.product', 'variant.attributeValues.attribute'])
            ->orderBy('id')
            ->get();
    }

    public function subtotal(): ?Price
    {
        $items = $this->purchasableItems();

        if ($items->isEmpty()) {
            return null;
        }

        $total = $items->first()->total();

        foreach ($items->skip(1) as $item) {
            $total = $total->add($item->total());
        }

        return $total;
    }

    /** @return SupportCollection<int, ShippingOption> */
    public function shippingOptions(): SupportCollection
    {
        return app(ShippingManager::class)->options($this);
    }

    public function selectShippingOption(ShippingOption|string $option): self
    {
        $handle = $option instanceof ShippingOption ? $option->handle : $option;

        if ($this->shippingOptions()->firstWhere('handle', $handle) === null) {
            throw new UnavailableShippingOptionException($handle);
        }

        $this->update(['shipping_option' => $handle]);

        return $this;
    }

    public function shippingOption(): ?ShippingOption
    {
        if ($this->shipping_option === null) {
            return null;
        }

        $option = $this->shippingOptions()->firstWhere('handle', $this->shipping_option);

        if ($option === null) {
            throw new StaleSelectedShippingOptionException($this->shipping_option);
        }

        return $option;
    }

    public function total(): ?Price
    {
        $total = $this->totalBeforeDiscounts();

        if ($total === null) {
            return null;
        }

        return $total->subtract($this->discountTotal());
    }

    /** @param array<string, mixed> $metadata */
    public function taxEstimate(
        ?Address $shippingAddress = null,
        ?Address $billingAddress = null,
        ?Address $originAddress = null,
        ?string $customerIdentifier = null,
        array $metadata = [],
    ): CartTaxEstimate {
        return app(CartTaxEstimator::class)->estimate(
            cart: $this,
            shippingAddress: $shippingAddress,
            billingAddress: $billingAddress,
            originAddress: $originAddress,
            customerIdentifier: $customerIdentifier,
            metadata: $metadata,
        );
    }

    /** @return SupportCollection<int, DiscountResult> */
    public function discounts(): SupportCollection
    {
        if ($this->items()->doesntExist()) {
            return collect();
        }

        return app(PromotionManager::class)->apply($this);
    }

    public function discountTotal(): Price
    {
        $discountTotal = $this->discounts()->reduce(
            fn (Price $total, DiscountResult $discount): Price => $total->add($discount->total()),
            Price::of(0),
        );
        $total = $this->totalBeforeDiscounts();

        if ($total === null) {
            return Price::of(0);
        }

        return $discountTotal->greaterThan($total) ? $total : $discountTotal;
    }

    public function applyPromotionCode(string $code): self
    {
        return app(PromotionManager::class)->attachCode($this, $code);
    }

    public function removePromotionCode(string $code): self
    {
        $code = PromotionManager::normalizeCode($code);
        $codes = $this->promotionCodes();

        if (! in_array($code, $codes, true)) {
            return $this;
        }

        $this->update([
            'promotion_codes' => array_values(array_filter(
                $codes,
                fn (string $attached): bool => $attached !== $code,
            )),
        ]);
        PromotionCodeRemoved::dispatch($this, $code);

        return $this;
    }

    /** @return array<int, string> */
    public function promotionCodes(): array
    {
        return $this->promotion_codes ?? [];
    }

    private function totalBeforeDiscounts(): ?Price
    {
        $subtotal = $this->subtotal();

        if ($subtotal === null) {
            return null;
        }

        $shippingOption = $this->shippingOption();

        return $shippingOption === null ? $subtotal : $subtotal->add($shippingOption->price);
    }

    /** @return class-string<CartItem> */
    protected function cartItemModel(): string
    {
        return app(ModelRegistry::class)->cartItem->class();
    }

    private function assertValidQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidCartQuantityException($quantity);
        }
    }

    public function assertProductCanBePurchased(Product $product, int $quantity): void
    {
        $this->assertVariantCanBePurchased($product->defaultVariant(), $quantity);
    }

    public function assertVariantCanBePurchased(ProductVariant $variant, int $quantity): void
    {
        if ($variant->status !== Visibility::Visible) {
            throw new UnavailableCartItemException($variant);
        }

        $minimum = $variant->minimumQuantity();
        $maximum = $variant->maximumQuantity();
        $stock = $variant->availableStock();

        if ($minimum !== null && $quantity < $minimum) {
            throw new CartQuantityBelowMinimumException($variant, $quantity, $minimum);
        }

        if ($maximum !== null && $quantity > $maximum) {
            throw new CartQuantityExceedsMaximumException($variant, $quantity, $maximum);
        }

        if (! $variant->allowsBackorders() && $stock !== null && $quantity > $stock) {
            throw new InsufficientCartStockException($variant, $quantity, $stock);
        }
    }

    private function resolveVariant(Product|ProductVariant $purchasable): ProductVariant
    {
        $variant = $purchasable instanceof Product
            ? $purchasable->defaultVariant()
            : $purchasable;

        if (! $variant->exists || ! $variant->product()->exists()) {
            throw new InvalidCartItemException($variant);
        }

        if ($variant->status !== Visibility::Visible) {
            throw new UnavailableCartItemException($variant);
        }

        return $variant->loadMissing('product');
    }

    /**
     * @template TKey of array-key
     *
     * @param  array<TKey, mixed>  $metadata
     * @return array<TKey, mixed>
     */
    private function normalizeMetadata(array $metadata): array
    {
        foreach ($metadata as &$value) {
            if (is_array($value)) {
                $value = $this->normalizeMetadata($value);
            }
        }

        if (! array_is_list($metadata)) {
            ksort($metadata);
        }

        return $metadata;
    }
}
