<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\Events\ProductUpdated;
use Larasell\Larasell\Catalog\Models\Category;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\ProductId;

final readonly class SetProductCategories
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  list<CategoryId>  $categoryIds  Every category the product is in; others are removed */
    public function handle(ProductId $productId, array $categoryIds, Origin $origin): void
    {
        $this->db->transaction(function () use ($origin, $productId, $categoryIds): void {
            $product = Product::query()->lockForUpdate()->findOrFail($productId->value);
            $categoryIds = CategoryId::values($categoryIds);
            Category::query()->findOrFail(array_values(array_unique($categoryIds)));

            $changes = $product->categories()->sync($categoryIds);

            if ($changes['attached'] !== [] || $changes['detached'] !== []) {
                ProductUpdated::dispatch($productId, ['categories'], $origin);
            }
        });
    }
}
