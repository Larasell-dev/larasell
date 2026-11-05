<?php

namespace Larasell\Larasell\Workflows;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Actions\DeleteProduct;
use Larasell\Larasell\Catalog\Models\Product;
use Larasell\Larasell\Seo\SeoResources;
use Larasell\Larasell\Seo\SeoUrlManager;
use LogicException;

/**
 * Deletes a product for good. Its URLs answer 410, or redirect permanently to the replacement while
 * that is available. Nothing changes when the catalog refuses the delete, e.g. for open orders.
 */
final readonly class DiscontinueProduct
{
    public function __construct(
        private ConnectionInterface $db,
        private DeleteProduct $delete,
        private SeoUrlManager $urls,
        private SeoResources $resources,
    ) {}

    public function handle(int $productId, ?int $replacementId = null): void
    {
        if ($replacementId === $productId) {
            throw new LogicException('A product cannot be its own replacement.');
        }

        $this->db->transaction(function () use ($productId, $replacementId): void {
            // Locked in ID order, so two workflows replacing each other's products cannot deadlock.
            $locked = Product::query()->whereKey(array_filter([$productId, $replacementId]))->orderBy('id')->lockForUpdate()->get();
            $replacement = $replacementId === null ? null : $locked->find($replacementId)
                ?? Product::query()->findOrFail($replacementId);

            $this->delete->handle($productId);

            if ($replacement !== null) {
                $this->urls->setReplacement($this->resources->forClass(Product::class), $productId, $replacement);
            }
        });
    }
}
