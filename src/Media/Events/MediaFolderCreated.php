<?php

namespace Larasell\Larasell\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Media\MediaFolderId;

/**
 * A folder was added to the media library.
 * Dispatched inside the action's transaction: a sync listener can refuse by throwing, which
 * undoes it.
 */
final readonly class MediaFolderCreated
{
    use Dispatchable;

    public function __construct(public MediaFolderId $folderId, public ?MediaFolderId $parentId, public string $name) {}
}
