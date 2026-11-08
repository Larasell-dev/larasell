<?php

namespace Larasell\Larasell\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Attachments\Attachments;
use Larasell\Larasell\Media\MediaId;

/**
 * A file was uploaded into the media library. Dispatched inside the action's transaction: a sync
 * listener can refuse by throwing, which undoes the upload and removes the stored file.
 */
final readonly class MediaUploaded
{
    use Dispatchable;

    public function __construct(public MediaId $mediaId, public Attachments $attachments = new Attachments) {}
}
