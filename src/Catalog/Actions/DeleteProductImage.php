<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductImageDeleted;
use Larasell\Larasell\Catalog\ImageId;
use Larasell\Larasell\Catalog\Models\ProductImage;
use Larasell\Larasell\Catalog\ProductId;

/** Deletes an image and removes it from its products. The stored file is kept. */
final readonly class DeleteProductImage
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(ImageId $imageId): void
    {
        $this->db->transaction(function () use ($imageId): void {
            $image = ProductImage::query()->lockForUpdate()->findOrFail($imageId->value);
            $productIds = ProductId::many($image->products()->orderBy('larasell_products.id')->pluck('larasell_products.id')->map(fn (mixed $id): int => (int) $id));

            $image->delete();

            ProductImageDeleted::dispatch($imageId, $productIds);
        });
    }
}
