<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductUpdated;
use Larasell\Larasell\Catalog\Models\Product;

final readonly class UpdateProduct
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * Changes the product as stored now, so a stale copy elsewhere cannot undo other changes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(int $productId, array $attributes): Product
    {
        return $this->db->transaction(function () use ($productId, $attributes): Product {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);
            $changed = array_keys($product->fill($attributes)->getDirty());

            if ($changed !== []) {
                $product->save();
                ProductUpdated::dispatch($product->id, $changed);
            }

            return $product;
        });
    }
}
