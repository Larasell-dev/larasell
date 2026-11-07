<?php

namespace Larasell\Larasell\Catalog\Actions;

use Larasell\Larasell\Attachments\Attachments;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Translatable;

/** What changes about a category; null keeps it. Its place in the tree changes with MoveCategory. */
final readonly class UpdateCategoryPayload
{
    public function __construct(
        /** All of its translations, e.g. `$category->name->with('de', 'Schuhe')`. */
        public ?Translatable $name = null,
        public ?Visibility $status = null,
        /** Handed to CategoryUpdated for the app's own listeners, which then runs even without changes. */
        public Attachments $attachments = new Attachments,
    ) {}
}
