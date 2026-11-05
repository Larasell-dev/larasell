<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductImageDeleted;
use Larasell\Larasell\Catalog\Models\ProductImage;

/** Deletes an image and removes it from its products. The stored file is kept. */
final readonly class DeleteProductImage
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(int $imageId): void
    {
        $this->db->transaction(function () use ($imageId): void {
            $image = ProductImage::query()->lockForUpdate()->findOrFail($imageId);
            $productIds = array_values($image->products()->orderBy('larasell_products.id')->pluck('larasell_products.id')->map(fn (mixed $id): int => (int) $id)->all());

            $image->delete();

            ProductImageDeleted::dispatch($image->id, $productIds);
        });
    }
}
