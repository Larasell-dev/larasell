<?php

namespace Larasell\Larasell\Catalog\Brands\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\Brands\BrandId;
use Larasell\Larasell\Catalog\Brands\Events\BrandDeleted;
use Larasell\Larasell\Catalog\Brands\Exceptions\BrandInUse;
use Larasell\Larasell\Catalog\Brands\Models\Brand;

/** Deletes a brand no product uses; otherwise BrandInUse says how many products do. */
final readonly class DeleteBrand
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(BrandId $brandId, Origin $origin): void
    {
        $this->db->transaction(function () use ($origin, $brandId): void {
            $brand = Brand::query()->lockForUpdate()->findOrFail($brandId->value);
            $products = $brand->products()->count();

            if ($products > 0) {
                throw BrandInUse::byProducts($brandId, $products);
            }

            $brand->delete();

            BrandDeleted::dispatch($brandId, origin: $origin);
        });
    }
}
