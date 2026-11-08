<?php

namespace Larasell\Larasell\Media\Actions;

use Larasell\Larasell\Media\MediaFolderId;
use Larasell\Larasell\Media\MediaFolderName;

/** A new folder: its name, and the folder it goes in (null for the top level). */
final readonly class CreateMediaFolderPayload
{
    public function __construct(public MediaFolderName $name, public ?MediaFolderId $parentId = null) {}
}
