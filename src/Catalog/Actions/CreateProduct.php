<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductCreated;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\ProductId;

final readonly class CreateProduct
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  array<string, mixed>  $attributes  Product attributes, e.g. name, price, sku, status */
    public function handle(array $attributes): Product
    {
        return $this->db->transaction(function () use ($attributes): Product {
            $product = Product::query()->create($attributes);

            ProductCreated::dispatch(ProductId::of($product));

            return $product;
        });
    }
}
