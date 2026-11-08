<?php

namespace Larasell\Larasell\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Media\MediaFolderId;

/**
 * An empty media folder was deleted.
 * Dispatched inside the action's transaction: a sync listener can refuse by throwing, which
 * undoes it.
 */
final readonly class MediaFolderDeleted
{
    use Dispatchable;

    public function __construct(public MediaFolderId $folderId, public ?MediaFolderId $parentId, public string $name) {}
}
