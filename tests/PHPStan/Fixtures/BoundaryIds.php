<?php

namespace Larasell\Larasell\Catalog\Actions;

use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Catalog\ProductId;

final readonly class TakesBareIds
{
    /** @param  list<int>  $categoryIds */
    public function handle(int $productId, array $categoryIds, ?int $id, int $position): void {}

    private function internal(int $productId): void {}
}

final readonly class TakesTypedIds
{
    /**
     * @param  list<CategoryId>  $categoryIds
     * @param  list<string>  $skuIds
     */
    public function handle(ProductId $productId, array $categoryIds, array $skuIds, int $quantity): void {}
}

namespace Larasell\Larasell\Catalog\Events;

final class CarriesBareId
{
    public int $variantId = 1;

    public function __construct(public ?int $productId) {}
}

namespace Larasell\Larasell\Catalog;

/** Not a boundary class, so not checked. */
final class InsideTheModule
{
    public function find(int $productId): void {}
}
