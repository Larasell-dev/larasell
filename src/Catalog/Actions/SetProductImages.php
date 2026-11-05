<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductUpdated;
use Larasell\Larasell\Catalog\ImageId;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\Models\ProductImage;
use Larasell\Larasell\Catalog\ProductId;

final readonly class SetProductImages
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  list<ImageId>  $imageIds  The product's images in order; the first is its thumbnail */
    public function handle(ProductId $productId, array $imageIds): void
    {
        $this->db->transaction(function () use ($productId, $imageIds): void {
            $product = Product::query()->lockForUpdate()->findOrFail($productId->value);
            $imageIds = ImageId::values($imageIds);
            ProductImage::query()->findOrFail(array_values(array_unique($imageIds)));

            if ($product->images()->pluck('larasell_product_images.id')->map(fn (mixed $id): int => (int) $id)->all() === $imageIds) {
                return;
            }

            $product->images()->sync(collect($imageIds)->mapWithKeys(
                fn (int $id, int $position): array => [$id => ['position' => $position]],
            )->all());

            ProductUpdated::dispatch($productId, ['images']);
        });
    }
}
