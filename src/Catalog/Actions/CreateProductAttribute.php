<?php

namespace Larasell\Larasell\Catalog\Actions;

use Illuminate\Database\ConnectionInterface;
use Larasell\Larasell\Catalog\Events\ProductAttributeCreated;
use Larasell\Larasell\Catalog\Models\ProductAttribute;

final readonly class CreateProductAttribute
{
    public function __construct(private ConnectionInterface $db) {}

    /** @param  array<string, mixed>  $attributes  e.g. slug, name, type */
    public function handle(array $attributes): ProductAttribute
    {
        return $this->db->transaction(function () use ($attributes): ProductAttribute {
            $attribute = ProductAttribute::query()->create($attributes);

            ProductAttributeCreated::dispatch($attribute->id);

            return $attribute;
        });
    }
}
