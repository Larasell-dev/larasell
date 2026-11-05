<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductVariantCreated;
use Larasell\Larasell\Catalog\Events\ProductVariantDeleted;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductAttributeValue;

/** Adds the variant for one combination of attribute values. The default variant goes away. */
final readonly class CreateProductVariant
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  list<int>  $valueIds  One value of each of the product's variant dimensions
     * @param  array<string, mixed>  $attributes  Variant attributes, e.g. sku, price, stock
     */
    public function handle(int $productId, array $valueIds, array $attributes = []): int
    {
        return $this->db->transaction(function () use ($productId, $valueIds, $attributes): int {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);
            $values = ProductAttributeValue::query()->findOrFail($valueIds);
            $defaultId = $product->variants()->where('is_default', true)->value('id');

            $variant = $product->createVariant($values->all(), $attributes);

            if ($defaultId !== null) {
                ProductVariantDeleted::dispatch((int) $defaultId, $product->id);
            }

            ProductVariantCreated::dispatch($variant->id, $product->id);

            return $variant->id;
        });
    }
}
