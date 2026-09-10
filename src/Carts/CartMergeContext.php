<?php

namespace Larasell\Larasell\Carts;

use Illuminate\Database\Eloquent\Collection;
use Larasell\Larasell\Models\Cart;
use Larasell\Larasell\Models\CartItem;
use Larasell\Larasell\Models\ProductVariant;

final readonly class CartMergeContext
{
    public function __construct(
        public Cart $destination,
        public Cart $source,
    ) {}

    /** @return Collection<int, CartItem> */
    public function sourceItems(): Collection
    {
        return $this->source->items;
    }

    /** @return Collection<int, CartItem> */
    public function destinationItems(): Collection
    {
        return $this->destination->items;
    }

    public function matchingDestinationItem(CartItem $item): ?CartItem
    {
        $key = $this->lineKey($item->variant, $item->metadata->all());

        return $this->destinationItems()->first(
            fn (CartItem $candidate): bool => $this->lineKey($candidate->variant, $candidate->metadata->all()) === $key,
        );
    }

    /**
     * @param  array<array-key, mixed>  $metadata
     */
    public function lineKey(ProductVariant $variant, array $metadata): string
    {
        return $variant->getKey().'|'.json_encode(self::normalizeMetadata($metadata), JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<array-key, mixed>  $metadata
     * @return array<array-key, mixed>
     */
    public static function normalizeMetadata(array $metadata): array
    {
        foreach ($metadata as &$value) {
            if (is_array($value)) {
                $value = self::normalizeMetadata($value);
            }
        }

        if (! array_is_list($metadata)) {
            ksort($metadata);
        }

        return $metadata;
    }
}
