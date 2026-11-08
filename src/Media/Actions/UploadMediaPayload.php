<?php

namespace Larasell\Larasell\Media\Actions;

use Illuminate\Http\UploadedFile;
use Larasell\Larasell\Attachments\Attachments;
use Larasell\Larasell\Media\AssetVisibility;
use Larasell\Larasell\Translatable;

/** A file to add to the media library: an image or video of a type in MediaType::mimeTypes(). */
final readonly class UploadMediaPayload
{
    public function __construct(
        public UploadedFile $file,
        public ?Translatable $alt = null,
        public AssetVisibility $visibility = AssetVisibility::Private,
        public ?Translatable $title = null,
        public ?Translatable $description = null,
        /** Handed to MediaUploaded for the app's own listeners. */
        public Attachments $attachments = new Attachments,
    ) {}
}
