<?php

namespace Larasell\Larasell\Media\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Larasell\Larasell\Media\MediaFolderId;

/**
 * A media folder got another name.
 * Dispatched inside the action's transaction: a sync listener can refuse by throwing, which
 * undoes it.
 */
final readonly class MediaFolderRenamed
{
    use Dispatchable;

    public function __construct(public MediaFolderId $folderId, public string $oldName, public string $name) {}
}
