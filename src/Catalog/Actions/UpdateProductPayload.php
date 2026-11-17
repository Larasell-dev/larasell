<?php

namespace Larasell\Larasell\Catalog\Actions;

use Larasell\Larasell\Attachments\Attachments;
use Larasell\Larasell\Catalog\Brands\BrandId;
use Larasell\Larasell\Catalog\Keep;
use Larasell\Larasell\Catalog\Manufacturers\ManufacturerId;

/** What changes about a product; attributes left out keep their value, and so do the brand and manufacturer unless given, null removing them. */
final readonly class UpdateProductPayload
{
    public function __construct(
        /** @var array<string, mixed> */
        public array $attributes = [],
        /** Handed to ProductUpdated for the app's own listeners, which then runs even without changes. */
        public Attachments $attachments = new Attachments,
        public BrandId|Keep|null $brandId = Keep::Value,
        public ManufacturerId|Keep|null $manufacturerId = Keep::Value,
    ) {}

    /** @return array<string, mixed> The attributes with the brand and manufacturer that change. */
    public function toAttributes(): array
    {
        return array_merge(
            $this->attributes,
            $this->brandId === Keep::Value ? [] : ['brand_id' => $this->brandId?->value],
            $this->manufacturerId === Keep::Value ? [] : ['manufacturer_id' => $this->manufacturerId?->value],
        );
    }
}
