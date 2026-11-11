<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\AttributeValueId;
use Larasell\Larasell\Catalog\Events\ProductUpdated;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductAttributeValue;
use Larasell\Larasell\Catalog\ProductId;

final readonly class SetProductAttributeValues
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  list<AttributeValueId>  $valueIds  Every attribute value the product has; others are removed */
    public function handle(ProductId $productId, array $valueIds, Origin $origin): void
    {
        $this->db->transaction(function () use ($origin, $productId, $valueIds): void {
            $product = Product::query()->lockForUpdate()->findOrFail($productId->value);
            $valueIds = AttributeValueId::values($valueIds);
            ProductAttributeValue::query()->findOrFail(array_values(array_unique($valueIds)));

            $changes = $product->attributeValues()->sync($valueIds);

            if ($changes['attached'] !== [] || $changes['detached'] !== []) {
                ProductUpdated::dispatch($productId, ['attribute_values'], $origin);
            }
        });
    }
}
