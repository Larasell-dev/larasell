<?php

namespace Larasell\Larasell\Media\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use Larasell\Larasell\Media\MediaFolderId;
use RuntimeException;

final class MediaFolderNotFound extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    public static function id(MediaFolderId $folderId): self
    {
        return self::structured(
            errorCode: 'larasell.media.folder-not-found',
            message: "Media folder [{$folderId}] does not exist.",
            why: 'It was deleted, e.g. by someone else while this request ran.',
            fix: 'Pick a folder that exists, or the top level.',
        );
    }
}
