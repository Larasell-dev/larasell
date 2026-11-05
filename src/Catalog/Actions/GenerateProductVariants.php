<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductVariantCreated;
use Larasell\Larasell\Catalog\Events\ProductVariantDeleted;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductAttribute;
use Larasell\Larasell\Catalog\Models\ProductVariant;

/** Creates a hidden variant for every combination of the product's values of the given attributes. */
final readonly class GenerateProductVariants
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  list<int>  $attributeIds  The variant dimensions, in order
     * @return list<int> The IDs of the variants for every combination, new or existing
     */
    public function handle(int $productId, array $attributeIds): array
    {
        return $this->db->transaction(function () use ($productId, $attributeIds): array {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);
            $attributes = ProductAttribute::query()->findOrFail($attributeIds)->sortBy(
                fn (ProductAttribute $attribute): int|false => array_search($attribute->id, $attributeIds, true),
            );
            $before = $product->variants()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

            $variantIds = array_values($product->generateVariants($attributes->values()->all())
                ->map(fn (ProductVariant $variant): int => $variant->id)->all());
            $after = $product->variants()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

            foreach (array_diff($before, $after) as $id) {
                ProductVariantDeleted::dispatch($id, $product->id);
            }

            foreach (array_diff($after, $before) as $id) {
                ProductVariantCreated::dispatch($id, $product->id);
            }

            return $variantIds;
        });
    }
}
