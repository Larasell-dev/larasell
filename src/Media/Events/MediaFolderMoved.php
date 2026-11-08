<?php

namespace Larasell\Larasell\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Media\MediaFolderId;

/**
 * A media folder moved into another folder, or to the top level (null). Its subfolders and media moved with it.
 * Dispatched inside the action's transaction: a sync listener can refuse by throwing, which
 * undoes it.
 */
final readonly class MediaFolderMoved
{
    use Dispatchable;

    public function __construct(public MediaFolderId $folderId, public ?MediaFolderId $oldParentId, public ?MediaFolderId $parentId) {}
}
