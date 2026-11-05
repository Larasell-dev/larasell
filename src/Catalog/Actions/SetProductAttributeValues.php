<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductUpdated;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductAttributeValue;

final readonly class SetProductAttributeValues
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  list<int>  $valueIds  Every attribute value the product has; others are removed */
    public function handle(int $productId, array $valueIds): void
    {
        $this->db->transaction(function () use ($productId, $valueIds): void {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);
            ProductAttributeValue::query()->findOrFail(array_values(array_unique($valueIds)));

            $changes = $product->attributeValues()->sync($valueIds);

            if ($changes['attached'] !== [] || $changes['detached'] !== []) {
                ProductUpdated::dispatch($product->id, ['attribute_values']);
            }
        });
    }
}
