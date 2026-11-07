<?php

namespace Larasell\Larasell\Catalog\Actions;

use Larasell\Larasell\Attachments\Attachments;
use Larasell\Larasell\Catalog\CategoryId;
use Larasell\Larasell\Enums\Visibility;
use Larasell\Larasell\Translatable;

/** A new category: it goes after its siblings, inside its parent or at the top level. */
final readonly class CreateCategoryPayload
{
    public function __construct(
        public Translatable $name,
        public Visibility $status = Visibility::Visible,
        public ?CategoryId $parentId = null,
        /** Handed to CategoryCreated for the app's own listeners. */
        public Attachments $attachments = new Attachments,
    ) {}
}
