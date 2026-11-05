<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductVariantDeleted;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductVariant;

final readonly class DeleteProductVariant
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(int $variantId): void
    {
        $this->db->transaction(function () use ($variantId): void {
            Product::query()->lockForUpdate()->findOrFail(ProductVariant::query()->findOrFail($variantId)->product_id);
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($variantId);

            $variant->delete();

            ProductVariantDeleted::dispatch($variant->id, $variant->product_id);
        });
    }
}
