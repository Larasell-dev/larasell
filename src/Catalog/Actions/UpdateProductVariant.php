<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductVariantUpdated;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductVariant;

final readonly class UpdateProductVariant
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  array<string, mixed>  $attributes */
    public function handle(int $variantId, array $attributes): ProductVariant
    {
        return $this->db->transaction(function () use ($variantId, $attributes): ProductVariant {
            // The product first, as UpdateProduct locks it before it writes the default variant.
            Product::query()->lockForUpdate()->findOrFail(ProductVariant::query()->findOrFail($variantId)->product_id);
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail($variantId);
            $changed = array_keys($variant->fill($attributes)->getDirty());

            if ($changed !== []) {
                $variant->save();
                ProductVariantUpdated::dispatch($variant->id, $variant->product_id, $changed);
            }

            return $variant;
        });
    }
}
