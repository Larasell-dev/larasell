<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\Events\ProductDeleted;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Catalog\VariantId;

/**
 * Deletes a product and its variants. Other modules may refuse by throwing from a listener of
 * ProductDeleted, e.g. Larasell\Larasell\Catalog\Exceptions\ProductInUse for open orders.
 */
final readonly class DeleteProduct
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(ProductId $productId, Origin $origin): void
    {
        $this->db->transaction(function () use ($origin, $productId): void {
            $product = Product::query()->lockForUpdate()->findOrFail($productId->value);
            $categoryIds = CategoryId::many($product->categories()->pluck('larasell_categories.id')->map(fn (mixed $id): int => (int) $id)->sort());
            $variantIds = VariantId::many($product->variants()->orderBy('id')->pluck('id')->map(fn (mixed $id): int => (int) $id));

            $product->delete();

            ProductDeleted::dispatch($productId, $product->name, $categoryIds, $variantIds, $origin);
        });
    }
}
