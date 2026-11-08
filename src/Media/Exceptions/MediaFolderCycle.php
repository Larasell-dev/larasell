<?php

namespace Larasell\Larasell\Media\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Media\MediaFolderId;
use RuntimeException;

final class MediaFolderCycle extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    public static function intoItself(MediaFolderId $folderId, MediaFolderId $parentId): self
    {
        return self::structured(
            errorCode: 'larasell.media.folder-cycle',
            message: "Media folder [{$folderId}] cannot move into media folder [{$parentId}], which is itself or one of its subfolders.",
            why: 'The folder tree would loop back on itself.',
            fix: 'Move the folder into one outside its own branch, or move the subfolder out first.',
        );
    }
}
