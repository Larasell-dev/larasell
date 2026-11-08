<?php

namespace Larasell\Larasell\Media\Actions;

use Larasell\Larasell\Attachments\Attachments;
use Larasell\Larasell\Media\AssetVisibility;
use Larasell\Larasell\Translatable;

/**
 * A media item's new details. The title, description and alt text each replace the old one in every
 * locale; null removes it.
 * The visibility applies to all its assets; null keeps theirs.
 */
final readonly class UpdateMediaPayload
{
    public function __construct(
        public ?Translatable $alt,
        public ?Translatable $title,
        public ?Translatable $description,
        public ?AssetVisibility $visibility = null,
        /** Handed to MediaUpdated for the app's own listeners. */
        public Attachments $attachments = new Attachments,
    ) {}
}
