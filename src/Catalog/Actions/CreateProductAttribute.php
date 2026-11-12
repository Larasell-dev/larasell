<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Audit\Origin;
use Larasell\Larasell\Catalog\AttributeId;
use Larasell\Larasell\Catalog\Events\ProductAttributeCreated;
use Larasell\Larasell\Catalog\Models\ProductAttribute;

final readonly class CreateProductAttribute
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  array<string, mixed>  $attributes  e.g. slug, name, type */
    public function handle(array $attributes, Origin $origin): ProductAttribute
    {
        return $this->db->transaction(function () use ($origin, $attributes): ProductAttribute {
            $attribute = ProductAttribute::query()->create($attributes);

            ProductAttributeCreated::dispatch(AttributeId::of($attribute), origin: $origin);

            return $attribute;
        });
    }
}
