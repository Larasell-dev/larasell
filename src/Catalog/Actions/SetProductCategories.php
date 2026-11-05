<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductUpdated;
use Larasell\Larasell\Catalog\Models\Category;
use Larasell\Larasell\Catalog\Models\Product;

final readonly class SetProductCategories
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  list<int>  $categoryIds  Every category the product is in; others are removed */
    public function handle(int $productId, array $categoryIds): void
    {
        $this->db->transaction(function () use ($productId, $categoryIds): void {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);
            Category::query()->findOrFail(array_values(array_unique($categoryIds)));

            $changes = $product->categories()->sync($categoryIds);

            if ($changes['attached'] !== [] || $changes['detached'] !== []) {
                ProductUpdated::dispatch($product->id, ['categories']);
            }
        });
    }
}
