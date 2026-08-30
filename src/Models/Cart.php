<?php

namespace Larasell\Larasell\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as SupportCollection;
use InvalidArgumentException;
use Larasell\Larasell\Discounts\DiscountResult;
use Larasell\Larasell\Discounts\PromotionManager;
use Larasell\Larasell\Enums\Currency;
use Larasell\Larasell\Events\PromotionCodeRemoved;
use Larasell\Larasell\Price;
use Larasell\Larasell\Shipping\ShippingManager;
use Larasell\Larasell\Shipping\ShippingOption;

/**
 * @property int $id
 * @property Currency $currency
 * @property string|null $session_id
 * @property int|null $user_id
 * @property string|null $shipping_option
 * @property array<int, string> $promotion_codes
 * @property Collection<int, CartItem> $items
 */
class Cart extends Model
{
    use HasFactory;

    protected $table = 'larasell_carts';

    protected $guarded = [];

    protected $attributes = [
        'promotion_codes' => '[]',
    ];

    protected $casts = [
        'currency' => Currency::class,
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

    public function add(Product $product, int $quantity = 1): CartItem
    {
        $this->assertValidQuantity($quantity);

        $item = $this->items()
            ->where('product_id', $product->getKey())
            ->first();

        $quantity = $quantity + ($item?->quantity ?? 0);
        $this->assertProductCanBePurchased($product, $quantity);

        return $this->items()->updateOrCreate(
            ['product_id' => $product->getKey()],
            ['quantity' => $quantity]
        );
    }

    public function set(Product $product, int $quantity): CartItem
    {
        $this->assertValidQuantity($quantity);
        $this->assertProductCanBePurchased($product, $quantity);

        return $this->items()->updateOrCreate(
            ['product_id' => $product->getKey()],
            ['quantity' => $quantity]
        );
    }

    public function remove(Product $product): void
    {
        $this->items()
            ->where('product_id', $product->getKey())
            ->delete();
    }

    public function clear(): void
    {
        $this->items()->delete();
    }

    public function quantity(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    public function subtotal(): ?Price
    {
        $items = $this->items()->with('product')->get();

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
            throw new InvalidArgumentException("Shipping option [{$handle}] is not available for this cart.");
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
            throw new InvalidArgumentException("Selected shipping option [{$this->shipping_option}] is no longer available for this cart.");
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

    protected function cartItemModel(): string
    {
        return app(ModelRegistry::class)->cartItem->class();
    }

    private function assertValidQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Cart item quantity must be at least 1.');
        }
    }

    public function assertProductCanBePurchased(Product $product, int $quantity): void
    {
        if ($product->min_quantity !== null && $quantity < $product->min_quantity) {
            throw new InvalidArgumentException('Cart item quantity is below the product minimum quantity.');
        }

        if ($product->max_quantity !== null && $quantity > $product->max_quantity) {
            throw new InvalidArgumentException('Cart item quantity exceeds the product maximum quantity.');
        }

        if (! $product->allow_backorders && $product->stock !== null && $quantity > $product->stock) {
            throw new InvalidArgumentException('Cart item quantity exceeds available product stock.');
        }
    }
}
