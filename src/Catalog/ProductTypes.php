<?php

namespace Larasell\Larasell\Catalog;

use BackedEnum;
use Closure;
use Larasell\Larasell\Catalog\Exceptions\InvalidProductType;

/**
 * The kinds of products the app sells, by key. The core registers "physical"; modules and apps
 * register their own, and may add capabilities to existing types with extend(), never remove them.
 */
final class ProductTypes
{
    /** @var array<string, ProductType> */
    private array $types = [];

    public function __construct(private readonly ProductCapabilities $capabilities) {}

    public static function keyOf(string|BackedEnum $type): string
    {
        return $type instanceof BackedEnum ? (string) $type->value : $type;
    }

    public function register(ProductType $type): void
    {
        if (isset($this->types[$type->key()])) {
            throw InvalidProductType::alreadyRegistered($type->key());
        }

        $this->types[$type->key()] = $this->checked($type);
    }

    /** @param  Closure(ProductType): ProductType  $extension */
    public function extend(string|BackedEnum $type, Closure $extension): void
    {
        $original = $this->get($type);
        $extended = $extension($original);

        if ($extended->key() !== $original->key() || array_diff($original->capabilities(), $extended->capabilities()) !== []) {
            throw InvalidProductType::cannotRemoveCapabilities($original->key());
        }

        $this->types[$original->key()] = $this->checked($extended);
    }

    public function get(string|BackedEnum $type): ProductType
    {
        return $this->types[self::keyOf($type)] ?? throw InvalidProductType::notRegistered(self::keyOf($type), array_keys($this->types));
    }

    public function has(string|BackedEnum $type): bool
    {
        return isset($this->types[self::keyOf($type)]);
    }

    /** @return list<ProductType> In registration order */
    public function all(): array
    {
        return array_values($this->types);
    }

    /** @return list<string> */
    public function keysWith(string $capability): array
    {
        return array_keys(array_filter($this->types, fn (ProductType $type): bool => $type->has($capability)));
    }

    private function checked(ProductType $type): ProductType
    {
        foreach ($type->capabilities() as $capability) {
            if (! $this->capabilities->has($capability)) {
                throw InvalidProductType::unknownCapability($type->key(), $capability);
            }
        }

        return $type;
    }
}
