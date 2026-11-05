<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductDeleted;
use Larasell\Larasell\Catalog\Models\Product;

/**
 * Deletes a product and its variants. Other modules may refuse by throwing from a listener of
 * ProductDeleted, e.g. Larasell\Larasell\Catalog\Exceptions\ProductInUse for open orders.
 */
final readonly class DeleteProduct
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(int $productId): void
    {
        $this->db->transaction(function () use ($productId): void {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);
            $categoryIds = array_values($product->categories()->pluck('larasell_categories.id')->map(fn (mixed $id): int => (int) $id)->sort()->all());
            $variantIds = array_values($product->variants()->orderBy('id')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());

            $product->delete();

            ProductDeleted::dispatch($product->id, $product->name, $categoryIds, $variantIds);
        });
    }
}
