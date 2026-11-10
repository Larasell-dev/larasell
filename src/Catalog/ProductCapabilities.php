<?php

namespace Larasell\Larasell\Catalog;

/**
 * What product types can do, by key, e.g. "stock" or "shipping". Each module registers the
 * capabilities whose rules it owns, and checks them with $variant->has(...), never type keys.
 */
final class ProductCapabilities
{
    /** @var list<string> */
    private array $capabilities = [];

    public function register(string $capability): void
    {
        if (! in_array($capability, $this->capabilities, true)) {
            $this->capabilities[] = $capability;
        }
    }

    public function has(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->capabilities;
    }
}
