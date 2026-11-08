<?php

namespace Larasell\Larasell\Media\Actions;

use Larasell\Larasell\Media\MediaFolderId;

/** Where a folder moves: into another folder, or to the top level (null). */
final readonly class MoveMediaFolderPayload
{
    public function __construct(public ?MediaFolderId $parentId) {}
}
