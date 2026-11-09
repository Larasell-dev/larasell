<?php

namespace Larasell\Larasell\Media\Actions;

use Larasell\Larasell\Attachments\Attachments;
use Larasell\Larasell\Media\AssetVisibility;
use Larasell\Larasell\Media\MediaFolderId;
use Larasell\Larasell\Media\MediaType;
use Larasell\Larasell\Translatable;

/** A file a provider already has, to add to the media library without uploading it. */
final readonly class RegisterExternalMediaPayload
{
    public function __construct(
        /** Its name in larasell.media.providers. */
        public string $provider,
        /** The provider's reference: a path on its disk, or e.g. a Mux asset ID. */
        public string $reference,
        public MediaType $type,
        /** The name the library shows it by. */
        public string $fileName,
        public AssetVisibility $visibility = AssetVisibility::Private,
        public ?Translatable $alt = null,
        public ?Translatable $title = null,
        public ?Translatable $description = null,
        /** Handed to MediaUploaded for the app's own listeners. */
        public Attachments $attachments = new Attachments,
        public ?MediaFolderId $folderId = null,
    ) {}
}
