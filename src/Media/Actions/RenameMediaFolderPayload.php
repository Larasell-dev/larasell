<?php

namespace Larasell\Larasell\Media\Actions;

use Larasell\Larasell\Media\MediaFolderName;

/** A folder's new name. */
final readonly class RenameMediaFolderPayload
{
    public function __construct(public MediaFolderName $name) {}
}
