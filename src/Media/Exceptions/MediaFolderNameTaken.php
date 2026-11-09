<?php

namespace Larasell\Larasell\Media\Exceptions;

use Larasell\Chronicle\Exceptions\HasStructuredException;
use Larasell\Chronicle\Exceptions\StructuredException;
use RuntimeException;
use Throwable;

final class MediaFolderNameTaken extends RuntimeException implements StructuredException
{
    use HasStructuredException;

    public static function named(string $name, ?Throwable $previous = null): self
    {
        return self::structured(
            errorCode: 'larasell.media.folder-name-taken',
            message: "Another media folder in the same place is already named [{$name}].",
            why: 'Folder names are unique among their siblings, ignoring case and surrounding spaces.',
            fix: 'Pick another name, or use the folder that already exists.',
            previous: $previous,
        );
    }
}
