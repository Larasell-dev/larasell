<?php

namespace Larasell\Larasell\Catalog\Brands\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\Brands\BrandId;
use Larasell\Larasell\Catalog\Brands\Events\BrandUpdated;
use Larasell\Larasell\Catalog\Brands\Models\Brand;

final readonly class UpdateBrand
{
    public function __construct(private ConnectionInterface $db) {}

    /** Changes the brand as stored now; dispatches BrandUpdated only when a field changed. */
    public function handle(BrandId $brandId, UpdateBrandPayload $payload, Origin $origin): Brand
    {
        return $this->db->transaction(function () use ($origin, $brandId, $payload): Brand {
            $brand = Brand::query()->lockForUpdate()->findOrFail($brandId->value);
            $changed = array_keys($brand->fill($payload->toAttributes())->getDirty());

            if ($changed !== []) {
                $brand->save();
                BrandUpdated::dispatch($brandId, $changed, origin: $origin);
            }

            return $brand;
        });
    }
}
