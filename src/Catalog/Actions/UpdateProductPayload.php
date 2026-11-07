<?php

namespace Larasell\Larasell\Catalog\Actions;

use Larasell\Larasell\Attachments\Attachments;

/** What changes about a product; attributes left out keep their value. */
final readonly class UpdateProductPayload
{
    public function __construct(
        /** @var array<string, mixed> */
        public array $attributes = [],
        /** Handed to ProductUpdated for the app's own listeners, which then runs even without changes. */
        public Attachments $attachments = new Attachments,
    ) {}
}
