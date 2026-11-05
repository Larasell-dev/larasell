<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductImageUpdated;
use Larasell\Larasell\Catalog\ImageId;
use Larasell\Larasell\Catalog\Models\ProductImage;

final readonly class UpdateProductImage
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  array<string, mixed>  $attributes */
    public function handle(ImageId $imageId, array $attributes): ProductImage
    {
        return $this->db->transaction(function () use ($imageId, $attributes): ProductImage {
            $image = ProductImage::query()->lockForUpdate()->findOrFail($imageId->value);
            $changed = array_keys($image->fill($attributes)->getDirty());

            if ($changed !== []) {
                $image->save();
                ProductImageUpdated::dispatch($imageId, $changed);
            }

            return $image;
        });
    }
}
