<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductImageCreated;
use Larasell\Larasell\Catalog\Models\ProductImage;

final readonly class CreateProductImage
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  array<string, mixed>  $attributes  file (an upload or a stored path), alt, meta */
    public function handle(array $attributes): ProductImage
    {
        return $this->db->transaction(function () use ($attributes): ProductImage {
            $image = ProductImage::query()->create($attributes);

            ProductImageCreated::dispatch($image->id);

            return $image;
        });
    }
}
