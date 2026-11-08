<?php

namespace Larasell\Larasell\Media\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Media\MediaFolderId;
use RuntimeException;

final class MediaFolderNotEmpty extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    public static function has(MediaFolderId $folderId, int $mediaCount, int $folderCount): self
    {
        return self::structured(
            errorCode: 'larasell.media.folder-not-empty',
            message: "Media folder [{$folderId}] still has {$mediaCount} media and {$folderCount} folders in it.",
            why: 'Only empty folders are deleted, so deleting one never deletes files.',
            fix: 'Move or delete what is in the folder first.',
        );
    }
}
