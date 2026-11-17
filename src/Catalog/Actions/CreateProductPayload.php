<?php

namespace Larasell\Larasell\Catalog\Actions;

use BackedEnum;
use Larasell\Larasell\Attachments\Attachments;
use Larasell\Larasell\Catalog\Brands\BrandId;
use Larasell\Larasell\Catalog\CoreProductType;
use Larasell\Larasell\Catalog\Manufacturers\ManufacturerId;

/** A new product, with its default variant. */
final readonly class CreateProductPayload
{
    public function __construct(
        /** @var array<string, mixed> Product attributes, e.g. name, price, sku, status */
        public array $attributes,
        /** Handed to ProductCreated for the app's own listeners. */
        public Attachments $attachments = new Attachments,
        /** A registered product type; it cannot change later. */
        public string|BackedEnum $type = CoreProductType::Physical,
        public ?BrandId $brandId = null,
        /** The legal producer, which the GPSR has product pages in the EU name. */
        public ?ManufacturerId $manufacturerId = null,
    ) {}
}
