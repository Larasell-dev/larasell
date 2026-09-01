<?php

namespace Larasell\Larasell\Shipping;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Larasell\Larasell\Models\Cart;

class ShippingManager
{
    /** @var array<int, class-string<ShippingMethod>> */
    private array $methods = [];

    public function __construct(private readonly Container $container) {}

    /** @param class-string<ShippingMethod> $method */
    public function register(string $method): void
    {
        if (! is_subclass_of($method, ShippingMethod::class)) {
            throw new InvalidArgumentException("Shipping method [{$method}] must extend ".ShippingMethod::class.'.');
        }

        if (! in_array($method, $this->methods, true)) {
            $this->methods[] = $method;
        }
    }

    /** @return Collection<int, ShippingOption> */
    public function options(Cart $cart): Collection
    {
        $options = collect($this->methods)
            ->flatMap(fn (string $method): Collection => $this->container->make($method)->options($cart))
            ->values();

        $duplicate = $options->groupBy('handle')->first(fn (Collection $group) => $group->count() > 1);

        if ($duplicate !== null) {
            throw new InvalidArgumentException("Shipping option handles must be unique. Duplicate handle [{$duplicate->first()->handle}].");
        }

        return $options;
    }
}
