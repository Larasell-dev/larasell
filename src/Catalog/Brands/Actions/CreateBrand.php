<?php

namespace Larasell\Larasell\Catalog\Brands\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\Brands\BrandId;
use Larasell\Larasell\Catalog\Brands\Events\BrandCreated;
use Larasell\Larasell\Catalog\Brands\Models\Brand;

final readonly class CreateBrand
{
    public function __construct(private ConnectionInterface $db) {}

    public function handle(CreateBrandPayload $payload, Origin $origin): Brand
    {
        return $this->db->transaction(function () use ($origin, $payload): Brand {
            $brand = Brand::query()->create(['name' => $payload->name, 'slug' => $payload->slug, 'description' => $payload->description]);

            BrandCreated::dispatch(BrandId::of($brand), origin: $origin);

            return $brand;
        });
    }
}
