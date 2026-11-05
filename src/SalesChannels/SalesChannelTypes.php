<?php

namespace Larasell\Larasell\SalesChannels;

/**
 * The kinds of sales channels the app knows, by key, e.g. "storefront". Modules and apps register the
 * types they build on, such as a POS or a marketplace integration; a channel can only have a known type.
 */
final class SalesChannelTypes
{
    /** @var list<string> */
    private array $types = [];

    public function register(string $type): void
    {
        if (! in_array($type, $this->types, true)) {
            $this->types[] = $type;
        }
    }

    public function has(string $type): bool
    {
        return in_array($type, $this->types, true);
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->types;
    }
}
