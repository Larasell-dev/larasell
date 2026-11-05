<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\AttributeValueId;
use Larasell\Larasell\Catalog\Events\ProductVariantCreated;
use Larasell\Larasell\Catalog\Events\ProductVariantDeleted;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductAttributeValue;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Catalog\VariantId;

/** Adds the variant for one combination of attribute values. The default variant goes away. */
final readonly class CreateProductVariant
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  list<AttributeValueId>  $valueIds  One value of each of the product's variant dimensions
     * @param  array<string, mixed>  $attributes  Variant attributes, e.g. sku, price, stock
     */
    public function handle(ProductId $productId, array $valueIds, array $attributes = []): VariantId
    {
        return $this->db->transaction(function () use ($productId, $valueIds, $attributes): VariantId {
            $product = Product::query()->lockForUpdate()->findOrFail($productId->value);
            $values = ProductAttributeValue::query()->findOrFail(AttributeValueId::values($valueIds));
            $defaultId = $product->variants()->where('is_default', true)->value('id');

            $variant = $product->createVariant($values->all(), $attributes);

            if ($defaultId !== null) {
                ProductVariantDeleted::dispatch(new VariantId((int) $defaultId), ProductId::of($product));
            }

            $variantId = VariantId::of($variant);
            ProductVariantCreated::dispatch($variantId, ProductId::of($product));

            return $variantId;
        });
    }
}
