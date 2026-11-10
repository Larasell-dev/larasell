<?php

namespace Larasell\Larasell\Catalog;

use BackedEnum;

/** What a kind of product is, e.g. physical: its key, its label and the capabilities it has. Immutable. */
final readonly class ProductType
{
    /** @param  list<string>  $capabilities */
    private function __construct(
        private string $key,
        private ?string $label = null,
        private array $capabilities = [],
    ) {}

    public static function make(string|BackedEnum $key): self
    {
        return new self(ProductTypes::keyOf($key));
    }

    /** @return ($label is null ? string : self) The label, or a copy with the given one */
    public function label(?string $label = null): string|self
    {
        return $label === null
            ? ($this->label ?? $this->key)
            : new self($this->key, $label, $this->capabilities);
    }

    /**
     * @param  list<string>|null  $capabilities
     * @return ($capabilities is null ? list<string> : self) The capabilities, or a copy with the given ones
     */
    public function capabilities(?array $capabilities = null): array|self
    {
        return $capabilities === null
            ? $this->capabilities
            : new self($this->key, $this->label, array_values(array_unique($capabilities)));
    }

    public function withCapability(string $capability): self
    {
        return $this->has($capability)
            ? $this
            : new self($this->key, $this->label, [...$this->capabilities, $capability]);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function has(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }
}
