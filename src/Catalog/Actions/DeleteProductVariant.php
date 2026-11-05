<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductVariantDeleted;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductVariant;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Catalog\VariantId;

final readonly class DeleteProductVariant
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(VariantId $variantId): void
    {
        $this->db->transaction(function () use ($variantId): void {
            Product::query()->lockForUpdate()->findOrFail(ProductVariant::query()->findOrFail($variantId->value)->product_id);
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($variantId->value);

            $variant->delete();

            ProductVariantDeleted::dispatch($variantId, new ProductId($variant->product_id));
        });
    }
}
