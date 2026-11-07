<?php

namespace Larasell\Larasell\Catalog\Actions;

use Larasell\Larasell\Attachments\Attachments;

/** A new product, with its default variant. */
final readonly class CreateProductPayload
{
    public function __construct(
        /** @var array<string, mixed> Product attributes, e.g. name, price, sku, status */
        public array $attributes,
        /** Handed to ProductCreated for the app's own listeners. */
        public Attachments $attachments = new Attachments,
    ) {}
}
