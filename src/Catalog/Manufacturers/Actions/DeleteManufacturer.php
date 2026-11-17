<?php

namespace Larasell\Larasell\Catalog\Manufacturers\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\Manufacturers\Events\ManufacturerDeleted;
use Larasell\Larasell\Catalog\Manufacturers\Exceptions\ManufacturerInUse;
use Larasell\Larasell\Catalog\Manufacturers\ManufacturerId;
use Larasell\Larasell\Catalog\Manufacturers\Models\Manufacturer;

/** Deletes a manufacturer no product uses; otherwise ManufacturerInUse says how many products do. */
final readonly class DeleteManufacturer
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(ManufacturerId $manufacturerId, Origin $origin): void
    {
        $this->db->transaction(function () use ($origin, $manufacturerId): void {
            $manufacturer = Manufacturer::query()->lockForUpdate()->findOrFail($manufacturerId->value);
            $products = $manufacturer->products()->count();

            if ($products > 0) {
                throw ManufacturerInUse::byProducts($manufacturerId, $products);
            }

            $manufacturer->delete();

            ManufacturerDeleted::dispatch($manufacturerId, origin: $origin);
        });
    }
}
