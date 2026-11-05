<?php

namespace Larasell\Larasell\Workflows;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Actions\CreateProduct;
use Larasell\Larasell\Catalog\Models\Product;

/** Creates a product with custom URLs: both are saved, or neither, e.g. when a path is taken. */
final readonly class CreateProductWithUrls
{
    public function __construct(
        private ConnectionInterface $db,
        private CreateProduct $create,
        private CustomUrls $urls,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  As for CreateProduct
     * @param  array<string, string|null>  $urls  Custom paths by locale, e.g. ['de' => 'winter-angebot']
     */
    public function handle(array $attributes, array $urls): Product
    {
        $plan = $this->urls->plan($urls);

        return $this->db->transaction(function () use ($attributes, $plan): Product {
            $product = $this->create->handle($attributes);
            $this->urls->apply($product, $plan);

            return $product;
        });
    }
}
