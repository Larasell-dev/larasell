<?php

namespace Larasell\Larasell\Workflows;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Actions\UpdateProduct;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Catalog\ProductId;

/** Updates a product and its custom URLs: both are saved, or neither, e.g. when a path is taken. */
final readonly class UpdateProductWithUrls
{
    public function __construct(
        private ConnectionInterface $db,
        private UpdateProduct $update,
        private CustomUrls $urls,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  As for UpdateProduct
     * @param  array<string, string|null>  $urls  Custom paths by locale, e.g. ['de' => 'winter-angebot']
     */
    public function handle(ProductId $productId, array $attributes, array $urls): Product
    {
        $plan = $this->urls->plan($urls);

        return $this->db->transaction(function () use ($productId, $attributes, $plan): Product {
            $product = $this->update->handle($productId, $attributes);
            $this->urls->apply($product, $plan);

            return $product;
        });
    }
}
