<?php

namespace Larasell\Larasell\Shipping;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Price;

abstract class ShippingMethod
{
    /** @var Collection<int, ShippingOption> */
    private Collection $options;

    abstract public function handle(Cart $cart): void;

    /** @return Collection<int, ShippingOption> */
    final public function options(Cart $cart): Collection
    {
        $this->options = collect();
        $this->handle($cart);

        return $this->options;
    }

    final protected function register(
        string $handle,
        string $name,
        Price $price,
        bool $requiresAddress = true,
    ): ShippingOption {
        if (trim($handle) === '') {
            throw new InvalidArgumentException('A shipping option handle is required.');
        }

        if (trim($name) === '') {
            throw new InvalidArgumentException('A shipping option name is required.');
        }

        $option = new ShippingOption($handle, $name, $price, static::class, $requiresAddress);
        $this->options->push($option);

        return $option;
    }
}
