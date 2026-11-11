<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\Events\ProductUpdated;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\ProductId;

final readonly class UpdateProduct
{
    public function __construct(private ConnectionInterface $db) {}

    /**
     * Changes the product as stored now, so a stale copy elsewhere cannot undo other changes.
     */
    public function handle(ProductId $productId, UpdateProductPayload $payload, Origin $origin): Product
    {
        return $this->db->transaction(function () use ($origin, $productId, $payload): Product {
            $product = Product::query()->lockForUpdate()->findOrFail($productId->value);
            $changed = array_keys($product->fill($payload->attributes)->getDirty());

            if ($changed !== []) {
                $product->save();
            }

            // Attachments are what the app saves with the product, so their listeners run either way.
            if ($changed !== [] || ! $payload->attachments->isEmpty()) {
                ProductUpdated::dispatch($productId, $changed, origin: $origin, attachments: $payload->attachments);
            }

            return $product;
        });
    }
}
