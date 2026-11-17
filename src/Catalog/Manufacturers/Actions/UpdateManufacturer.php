<?php

namespace Larasell\Larasell\Catalog\Manufacturers\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\Manufacturers\Events\ManufacturerUpdated;
use Larasell\Larasell\Catalog\Manufacturers\ManufacturerId;
use Larasell\Larasell\Catalog\Manufacturers\Models\Manufacturer;

final readonly class UpdateManufacturer
{
    public function __construct(private ConnectionInterface $db) {}

    /** Changes the manufacturer as stored now; dispatches ManufacturerUpdated only when a field changed. */
    public function handle(ManufacturerId $manufacturerId, UpdateManufacturerPayload $payload, Origin $origin): Manufacturer
    {
        return $this->db->transaction(function () use ($origin, $manufacturerId, $payload): Manufacturer {
            $manufacturer = Manufacturer::query()->lockForUpdate()->findOrFail($manufacturerId->value);
            $changed = array_keys($manufacturer->fill($payload->toAttributes())->getDirty());

            if ($changed !== []) {
                $manufacturer->save();
                ManufacturerUpdated::dispatch($manufacturerId, $changed, origin: $origin);
            }

            return $manufacturer;
        });
    }
}
