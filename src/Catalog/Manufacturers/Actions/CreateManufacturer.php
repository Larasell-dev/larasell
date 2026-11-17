<?php

namespace Larasell\Larasell\Catalog\Manufacturers\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\Manufacturers\Events\ManufacturerCreated;
use Larasell\Larasell\Catalog\Manufacturers\ManufacturerId;
use Larasell\Larasell\Catalog\Manufacturers\Models\Manufacturer;

final readonly class CreateManufacturer
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CreateManufacturerPayload $payload, Origin $origin): Manufacturer
    {
        return $this->db->transaction(function () use ($origin, $payload): Manufacturer {
            $manufacturer = Manufacturer::query()->create($payload->toAttributes());

            ManufacturerCreated::dispatch(ManufacturerId::of($manufacturer), origin: $origin);

            return $manufacturer;
        });
    }
}
