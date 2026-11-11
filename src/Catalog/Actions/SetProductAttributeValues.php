<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\AttributeCardinality;
use Larasell\Larasell\Catalog\AttributeScope;
use Larasell\Larasell\Catalog\AttributeValueId;
use Larasell\Larasell\Catalog\Events\ProductUpdated;
use Larasell\Larasell\Catalog\Exceptions\InvalidAttributeValues;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductAttributeValue;
use Larasell\Larasell\Catalog\ProductId;

final readonly class SetProductAttributeValues
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * @param  list<AttributeValueId>  $valueIds  Every attribute value the product has; others are removed
     *
     * @throws InvalidAttributeValues when a single-value attribute gets several
     */
    public function handle(ProductId $productId, array $valueIds, Origin $origin): void
    {
        $this->db->transaction(function () use ($origin, $productId, $valueIds): void {
            $product = Product::query()->lockForUpdate()->findOrFail($productId->value);
            $valueIds = AttributeValueId::values($valueIds);
            $values = ProductAttributeValue::query()->with('attribute')->findOrFail(array_values(array_unique($valueIds)));

            // Only attributes variants use: the old variant dimensions keep every value their
            // variants are made of on the product, as product-only attributes.
            foreach ($values->groupBy('product_attribute_id') as $ofOne) {
                $attribute = $ofOne->firstOrFail()->attribute;

                if ($ofOne->count() > 1 && $attribute->cardinality === AttributeCardinality::Single && $attribute->scope !== AttributeScope::Product) {
                    throw InvalidAttributeValues::tooMany($attribute->slug);
                }
            }

            $changes = $product->attributeValues()->sync($valueIds);

            if ($changes['attached'] !== [] || $changes['detached'] !== []) {
                ProductUpdated::dispatch($productId, ['attribute_values'], $origin);
            }
        });
    }
}
