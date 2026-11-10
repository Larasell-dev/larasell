<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductCreated;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\ProductId;
use Larasell\Larasell\Catalog\ProductTypes;

final readonly class CreateProduct
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CreateProductPayload $payload): Product
    {
        return $this->db->transaction(function () use ($payload): Product {
            $product = Product::query()->create([...$payload->attributes, 'type' => ProductTypes::keyOf($payload->type)]);

            ProductCreated::dispatch(ProductId::of($product), $payload->attachments);

            return $product;
        });
    }
}
